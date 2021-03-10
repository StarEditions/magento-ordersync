<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Letsprintondemand\OrderSync\Model;

use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;
use Magento\Sales\Model\ResourceModel\Order\Status as StatusResource;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;

class OrderupdateManagement implements \Letsprintondemand\OrderSync\Api\OrderupdateManagementInterface
{

    protected $resultPageFactory;
    protected $jsonHelper;
    protected $helper;
    protected $order;
    protected $statusFactory;
    protected $statusResourceFactory;
    private $orderStatusCollection;
    private $request;
    private $orderModel;
    private $trackFactory;
    private $shipmentFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     */

    public function __construct(        
        OrderStatusCollection $orderStatusCollection,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Letsprintondemand\OrderSync\Helper\Data $helper,
        \Magento\Sales\Model\OrderFactory $order,
        StatusFactory $statusFactory,
        \Magento\Framework\Webapi\Rest\Request $request,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \Magento\Shipping\Model\ShipmentNotifier $shipmentFactory,
        StatusResourceFactory $statusResourceFactory,
        \Magento\Sales\Model\Convert\Order $orderModel,
        \Psr\Log\LoggerInterface $logger
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
     * @return \Magento\Framework\Controller\ResultInterface
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
                        } catch(\Exception $e) {
                            $this->logger->info($e->getMessage());
                            return $e->getMessage();
                        }
                    }
				}
            	return json_encode(true);
			} catch(\Exception $e) {
				return json_encode($e->getMessage());
			}
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return json_encode($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return json_encode($e->getMessage());
        }
    }

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
