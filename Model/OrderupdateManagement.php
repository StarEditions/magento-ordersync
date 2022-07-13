<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace StarEditions\OrderSync\Model;

use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Shipping\Model\ShipmentNotifier;
use Psr\Log\LoggerInterface;
use StarEditions\OrderSync\Api\OrderupdateManagementInterface;
use StarEditions\OrderSync\Helper\Data;

class OrderupdateManagement implements OrderupdateManagementInterface
{

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var OrderFactory
     */
    protected $order;

    /**
     * @var StatusFactory
     */
    protected $statusFactory;

    /**
     * @var StatusResourceFactory
     */
    protected $statusResourceFactory;

    /**
     * @var OrderStatusCollection
     */
    private $orderStatusCollection;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var \Magento\Sales\Model\Convert\Order
     */
    private $orderModel;

    /**
     * @var TrackFactory
     */
    private $trackFactory;

    /**
     * @var ShipmentNotifier
     */
    private $shipmentFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param OrderStatusCollection $orderStatusCollection
     * @param PageFactory $resultPageFactory
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param Data $helper
     * @param OrderFactory $order
     * @param StatusFactory $statusFactory
     * @param Request $request
     * @param TrackFactory $trackFactory
     * @param ShipmentNotifier $shipmentFactory
     * @param StatusResourceFactory $statusResourceFactory
     * @param \Magento\Sales\Model\Convert\Order $orderModel
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderStatusCollection $orderStatusCollection,
        PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        Data $helper,
        OrderFactory $order,
        StatusFactory $statusFactory,
        Request $request,
        TrackFactory $trackFactory,
        ShipmentNotifier $shipmentFactory,
        StatusResourceFactory $statusResourceFactory,
        \Magento\Sales\Model\Convert\Order $orderModel,
        LoggerInterface $logger
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->orderModel = $orderModel;
        $this->trackFactory = $trackFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->request = $request;
        $this->statusFactory = $statusFactory;
        $this->statusResourceFactory = $statusResourceFactory;
        $this->orderStatusCollection=$orderStatusCollection;
        $this->helper = $helper;
        $this->order = $order;
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function postOrderupdate()
    {
        try {
        	$postData = $this->request->getBodyParams();
        	$this->logger->info("Callback URL DATA");
        	$this->logger->info(json_encode($postData));
			try {
				if($postData['external_ref']) {
					$order = $this->order->create()->loadByIncrementId($postData['external_ref']);
                    $order_status = $this->getAllOrderStatus($postData['status']);
                    $order->setState($order_status)->setStatus($order_status);
                    $order->save();
                    if($order->canShip()) {
                        $shipment = $this->orderModel->toShipment($order);
                        foreach ($order->getAllItems() AS $orderItem) {
                            $storeBrand = $this->helper->getStoreBrandValue();
                            $productManufacturer = $orderItem->getProduct()->getAttributeText('manufacturer');
                            if ($productManufacturer == $storeBrand) {
                                $qtyShipped = $orderItem->getQtyToShip();
                                $shipmentItem = $this->orderModel->itemToShipmentItem($orderItem)->setQty($qtyShipped);
                                $shipment->addItem($shipmentItem);
                            }
                        }
                        $shipment->register();
                        $shipment->getOrder()->setIsInProcess(true);
                        $shipment->getExtensionAttributes()->setSourceCode('default');
                        try {
                            $data = array(
                                'carrier_code' => $postData['shipping_carrier'],
                                'title' => $postData['tracking_company'],
                                'number' => $postData['tracking_number'],
                            );
                            $track = $this->trackFactory->create()->addData($data);
                            $shipment->addTrack($track)->save();
                            $shipment->getOrder()->save();
                            $shipment->save();
                            return true;
                        } catch(Exception $e) {
                            $this->logger->info($e->getMessage());
                            return $e->getMessage();
                        }
                    }
				}
            	return json_encode(true);
			} catch(Exception $e) {
				return json_encode($e->getMessage());
			}
        } catch (LocalizedException $e) {
            return json_encode($e->getMessage());
        } catch (Exception $e) {
            $this->logger->critical($e);
            return json_encode($e->getMessage());
        }
    }

    /**
     * @param $orderStatus
     * @return false|mixed|string
     * @throws AlreadyExistsException
     */
    public function getAllOrderStatus($orderStatus){
    	$collection = $this->orderStatusCollection;
    	foreach($collection as $status) {
    		if($status['label'] == $orderStatus) {
    			return $status['status'];
    		}
    	}
    	$statusResource = $this->statusResourceFactory->create();
        $status = $this->statusFactory->create();
        $status->setData([
            'status' => strtolower($orderStatus),
            'label' => $orderStatus,
        ]);
        $statusResource->save($status);
        if($statusResource) {
        	$status->assignState(strtolower($orderStatus), true, true);
        	return strtolower($orderStatus);
        }
	    return false;
	}
}
