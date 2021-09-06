<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Letsprintondemand\OrderSync\Observer\Checkout;

class OnepageControllerSuccessAction implements \Magento\Framework\Event\ObserverInterface
{
    protected $helper;
    
    protected $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Letsprintondemand\OrderSync\Helper\Data $helper
    ){
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $orderData = $observer->getEvent()->getOrder();
        $taxes_included = FALSE;
        if($orderData['base_tax_amount']) {
            $taxes_included = true;
        }
        $paymentData = $orderData->getPayment();
        $shippingAddress = $orderData->getShippingAddress();
        $billingAddress = $orderData->getBillingAddress();
        $lineItems = [];
        $product_options = [];

        $storeBrand = $this->helper->getStoreBrandValue();
        $lessPrice = 0;
        foreach($orderData->getAllItems() as $_item) {
            $productManufacturer = $_item->getProduct()->getAttributeText('manufacturer');
            if($productManufacturer == $storeBrand) {
                if(isset($_item['product_options']['options'])) {
                    $productOptions = $_item['product_options']['options'];
                    foreach($productOptions as $option) {
                        $product_options[] = $option['value'];
                    }
                }
                $lineItems[] = [
                    "id"=> $_item['item_id'], // REQUIRED
                    "variant_id" => '',
                    "title"=> $_item['name'], // REQUIRED
                    "quantity"=> $_item['qty_ordered'],// REQUIRED
                    "sku"=>  $_item['sku'],// REQUIRED
                    "variant_title"=> $_item['product_type'],
                    "vendor"=> '',
                    "name"=> $_item['name'],
                    "properties" => [
                        $product_options
                    ],
                    "grams" => $_item['row_weight'],
                    "price" => $_item['price'], // REQUIRED
                    "total_discount" => $_item['discount_amount'],
                    "fulfillment_service" => 'star-editions',
                    "fulfillment_status" => ''
                ];
            } else {
                $lessPrice = $lessPrice + ($_item['price']*$_item['qty_ordered']);
            }
        }
        if($lineItems) {
            $data = array(
                "id" => $orderData['entity_id'],
                "email" => $orderData['customer_email'],// REQUIRED customer email
                "order_ref" => $orderData['increment_id'], // REQUIRED Order Ref
                "note" => $orderData['customer_note'],
                "total_price" => ((float)$orderData['grand_total'] - (float)$lessPrice), // REQUIRED Total order value 
                "subtotal_price" => ((float)$orderData['subtotal'] - (float)$lessPrice),
                "total_weight" => $orderData['weight'], // Weight in grams
                "total_tax" => $orderData['base_tax_amount'],
                "taxes_included" => $taxes_included, // TRUE or FALSE
                "currency" => $orderData['order_currency_code'], // REQUIRED 
                "financial_status" => 'paid', // REQUIRED payment status
                "total_discounts" => $orderData['discount_amount'],
                "total_line_items_price" => '',
                "cancelled_at" => '',
                "cancel_reason" => '',
                "browser_ip" => '', 
                "processing_method" => $paymentData['method'], // Payment method
                "source_name" => 'Magento', // source type eg. WordPress/Magento/Bespoke
                "fulfillment_status" => '',
                "total_shipping_price_set" => '',
                "shipping_lines" => [[
                    "title" => $orderData['shipping_description'],
                    "price" => $orderData['shipping_amount'], // REQUIRED
                    "code" => $orderData['shipping_method']
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
            if($apiUrl && $store_name && $token) {
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
                } catch(\Exception $e) {
                    $this->logger->debug($e->getMessage());
                }            
            }
        }        
    




    }
}

