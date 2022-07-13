<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace StarEditions\OrderSync\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use StarEditions\OrderSync\Helper\Data;
use Throwable;
use Zend_Log;
use Zend_Log_Writer_Stream;

class OrderSaveAfter implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderRepositoryInterface $orderRepository
     * @param Data $helper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OrderRepositoryInterface $orderRepository,
        Data $helper,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurableType,
        \Magento\Catalog\Model\ProductRepository $productRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->orderRepository = $orderRepository;
        $this->helper = $helper;
        $this->configurableType = $configurableType;
        $this->productRepository = $productRepository;
        $this->initLogger();
    }

    /**
     * @return void
     * @throws \Zend_Log_Exception
     */
    private function initLogger()
    {
        $writer = new Zend_Log_Writer_Stream(BP . '/var/log/ordersync_errors.log');
		$this->logger = new Zend_Log();
		$this->logger->addWriter($writer);
    }

    /**
     * @param Observer $observer
     * @return OrderSaveAfter
     */
    public function execute(
        Observer $observer
    ) {
        $order = $observer->getEvent()->getOrder();

        if ($this->checkOrder($order) && empty($order->getData('order_sync'))) {
            $orderStatuses = $this->scopeConfig->getValue('ordersync/settings/order_export_statuses', ScopeInterface::SCOPE_STORE);

            if ($orderStatuses && strpos($orderStatuses, ',') !== false) {
                $orderStatuses = explode(",", $orderStatuses);
            }

            if ((is_array($orderStatuses) && in_array($order->getStatus(), $orderStatuses)) || $orderStatuses == $order->getStatus()) {
                try {
                    $this->exportOrder($order);
                } catch (Throwable $th) {
                    $this->logger->info('OrderId: ' . $order->getId() . ' | Message: ' . $th->getMessage());
                }
            }
        }

        return $this;
    }

    /**
     * @param $order
     * @throws NoSuchEntityException
     */
    public function exportOrder($order)
    {
        $taxes_included = FALSE;
        if ($order['base_tax_amount']) {
            $taxes_included = true;
        }

        $paymentData = $order->getPayment();
        $shippingAddress = $order->getShippingAddress();
        $billingAddress = $order->getBillingAddress();
        $lineItems = [];
        $storeBrand = $this->helper->getStoreBrandValue();
        $lessPrice = 0;

        foreach ($order->getAllItems() as $_item) {
            if ($_item->getProductType() != 'configurable') {
                $product_options = [];
                $product = $_item->getProduct();
                $parentIds = $this->configurableType->getParentIdsByChild($product->getId());
                $parentId = array_shift($parentIds);
                $productManufacturer = $product->getAttributeText('manufacturer');
                $itemPrice = $_item->getPrice();
                $rowWeight = $_item->getRowWeight();
                $discountAmount = $_item->getDiscountAmount();

                if ($parentId) {
                    $itemPrice = $_item->getParentItem()->getPrice();
                    $rowWeight = $_item->getParentItem()->getRowWeight();
                    $discountAmount = $_item->getParentItem()->getDiscountAmount();

                    if (!$productManufacturer) {
                        $parentProduct = $this->productRepository->getById($parentId);
                        $productManufacturer = $parentProduct->getAttributeText('manufacturer');
                    }
                }

                if ($productManufacturer == $storeBrand) {
                    if (isset($_item['product_options']['info_buyRequest']['options'])) {
                        $productOptions = $_item['product_options']['info_buyRequest']['options'];
                        foreach ($productOptions as $option) {
                            $product_options[] = $option;
                        }
                    }
                    $lineItems[] = [
                        "id"=> $_item->getItemId(), // REQUIRED
                        "variant_id" => '',
                        "title"=> $_item->getName(), // REQUIRED
                        "quantity"=> $_item->getQtyOrdered(),// REQUIRED
                        "sku"=>  $_item->getSku(),// REQUIRED
                        "variant_title"=> $_item->getProductType(),
                        "vendor"=> '',
                        "name"=> $_item->getName(),
                        "properties" => [
                            $product_options
                        ],
                        "grams" => $rowWeight,
                        "price" => $itemPrice, // REQUIRED
                        "total_discount" => $discountAmount,
                        "fulfillment_service" => 'star-editions',
                        "fulfillment_status" => ''
                    ];
                } else {
                    $lessPrice = $lessPrice + ($itemPrice * $_item->getQtyOrdered());
                }
            }
        }

        if ($lineItems) {
            $data = array(
                "id" => $order['entity_id'],
                "email" => $order['customer_email'],// REQUIRED customer email
                "order_ref" => $order['increment_id'], // REQUIRED Order Ref
                "note" => $order['customer_note'],
                "total_price" => ((float)$order['grand_total'] - (float)$lessPrice), // REQUIRED Total order value
                "subtotal_price" => ((float)$order['subtotal'] - (float)$lessPrice),
                "total_weight" => $order['weight'], // Weight in grams
                "total_tax" => $order['base_tax_amount'],
                "taxes_included" => $taxes_included, // TRUE or FALSE
                "currency" => $order['order_currency_code'], // REQUIRED
                "financial_status" => 'paid', // REQUIRED payment status
                "total_discounts" => $order['discount_amount'],
                "total_line_items_price" => '',
                "cancelled_at" => '',
                "cancel_reason" => '',
                "browser_ip" => '',
                "processing_method" => $paymentData['method'], // Payment method
                "source_name" => 'Magento', // source type eg. WordPress/Magento/Bespoke
                "fulfillment_status" => '',
                "total_shipping_price_set" => '',
                "shipping_lines" => [[
                    "title" => $order['shipping_description'],
                    "price" => $order['shipping_amount'], // REQUIRED
                    "code" => $order['shipping_method']
                ]],
                "billing_address" => array(
                    "address1" => isset($billingAddress->getStreet(1)[0]) ? $billingAddress->getStreet(1)[0] : '', // REQUIRED
                    "address2" => isset($billingAddress->getStreet(2)[1]) ? $billingAddress->getStreet(2)[1] : null,
                    "phone" => $billingAddress['telephone'],
                    "city" => $billingAddress['city'],// REQUIRED
                    "zip" => $billingAddress['postcode'],// REQUIRED
                    "province" => $billingAddress['region'],
                    "name" => $billingAddress['firstname'].' '.$billingAddress['lastname'],// REQUIRED Customer Name
                    "country_code" => $billingAddress['country_id'] // REQUIRED
                ),
                "shipping_address" => array(
                    "address1" => isset($shippingAddress->getStreet(1)[0]) ? $shippingAddress->getStreet(1)[0] : '', // REQUIRED
                    "address2" => isset($shippingAddress->getStreet(2)[1]) ? $shippingAddress->getStreet(2)[1] : null,
                    "phone" => $shippingAddress['telephone'],
                    "city" => $shippingAddress['city'],// REQUIRED
                    "zip" => $shippingAddress['postcode'],// REQUIRED
                    "province" => $shippingAddress['region'],
                    "name" => $shippingAddress['firstname'].' '.$shippingAddress['lastname'],// REQUIRED Customer Name
                    "country_code" => $shippingAddress['country_id'] // REQUIRED
                ),
                "line_items" => $lineItems
            );

            $apiUrl = $this->helper->getStoreApiUrl();
            $store_name = $this->helper->getStoreName();
            $token = $this->helper->getApiToken();
            if ($apiUrl && $store_name && $token) {
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $apiUrl.$store_name.'/order');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    $headers = array();
                    $headers[] = 'Accept: application/json';
                    $headers[] = 'Authorization: Bearer '.$token;
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $result = curl_exec($ch);
                    curl_close($ch);
                    $order->setData('order_sync', 1);
                } catch(Exception $e) {
                    $order->setData('order_sync', 0);
                    $this->logger->info("Inner Catch: ");
                    $this->logger->info($e->getMessage());
                }
            }

            $this->orderRepository->save($order);
        }
    }

    /**
     * @param $order
     * @return bool
     */
    private function checkOrder($order)
    {
        try {
            $order = $this->orderRepository->get($order->getId());
            if (!empty($order->getAllItems())) {
                return true;
            }
        } catch (Throwable $th) {
            return false;
        }

        return false;
    }
}
