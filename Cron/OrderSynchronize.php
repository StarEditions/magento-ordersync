<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Letsprintondemand\OrderSync\Cron;

use Magento\Framework\App\Config\Storage\WriterInterface;

class OrderSynchronize
{

    protected $scopeCollectionFactory;

    protected $orderCollectionFactory;

    protected $configWriter;

    protected $helper;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(    	
    	\Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $scopeCollectionFactory,
    	WriterInterface $configWriter,
    	\Letsprintondemand\OrderSync\Helper\Data $helper,
    	\Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
    ) {        
        $this->helper = $helper;
        $this->configWriter = $configWriter;
        $this->scopeCollectionFactory = $scopeCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {    	
    	$digits = 6;
		$uniqueRequestID = rand(pow(10, $digits-1), pow(10, $digits)-1);
    	$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ordersync_errors.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer);
    	try {
	    	$processLockFactory = $this->scopeCollectionFactory->create();
	    	$processLockResult = $processLockFactory->addFieldToFilter('path', ['eq' => 'ordersync/cron/processlock'])->getFirstItem();
	    	$isProcessLocked = $processLockResult->getValue();	    	
    	} catch(\Exception $e) {
    		$logger->info("Catch1: ");
    		$logger->info(json_encode($e->getMessage()));
    		$isProcessLocked = 0;
    	}
    	try {
	    	if(!$isProcessLocked) {
	    		//sets process lock to 1 so that another CRON cannot be run until the previous CRON is running
	    		$this->configWriter->save('ordersync/cron/processlock', 1);
	    		$orderDataCollection = $this->orderCollectionFactory->create()	    			
					->addAttributeToSelect('*')
					->addFieldToFilter('created_at', array(
						'from'     => strtotime('-1 day', time()),
						'to'       => time(),
						'datetime' => true
					))
					->addFieldToFilter('order_sync', 0);				
		        if($orderDataCollection->getSize()) {		        	
		        	foreach($orderDataCollection as $orderData) {
				        $taxes_included = FALSE;
				        if($orderData['base_tax_amount']) {
				            $taxes_included = true;
				        }
				        $paymentData = $orderData->getPayment();
				        $shippingAddress = $orderData->getShippingAddress();
				        $billingAddress = $orderData->getBillingAddress();
				        $lineItems = [];
				        $storeBrand = $this->helper->getStoreBrandValue();
				        $lessPrice = 0;				        
				        foreach($orderData->getAllItems() as $_item) {
				        	if($_item->getProductType() != 'configurable') {
				        		$product_options = [];
					            $productManufacturer = $_item->getProduct()->getAttributeText('manufacturer');
					            if($productManufacturer == $storeBrand) {
					                /*if(isset($_item['product_options']['options'])) {
					                    $productOptions = $_item['product_options']['options'];
					                    foreach($productOptions as $option) {
					                        $product_options[] = $option['value'];
					                    }
					                }*/
					                if(isset($_item['parent_item_id'], $_item['product_options']['info_buyRequest']['options'])) {
					                	$productOptions = $_item['product_options']['info_buyRequest']['options'];
					                	foreach($productOptions as $option) {
					                        $product_options[] = $option;
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
		        					$orderData->setData('order_sync', 1)->save();
				                } catch(\Exception $e) {
		        					$orderData->setData('order_sync', 0)->save();
		        					$logger->info("Inner Catch: ");
				                    $logger->info($e->getMessage());
				                }
				            }
				        }
		        	}	        	
		        }		        
	    		//At the end set process lock to 0, so next CRON can proceed
	    		$this->configWriter->save('ordersync/cron/processlock', 0);
	    	} else {	    		
	    		try {
	    			$processLockMaxFactory = $this->scopeCollectionFactory->create();
		    		$maxTry = $processLockMaxFactory->addFieldToFilter('path', ['eq' => 'ordersync/cron/processlock_maxtry'])->getFirstItem();
		    		$maxTryValue = (int)$maxTry->getValue();		    		
	    		} catch(\Exception $e) {	    			
	    			$logger->info("Catch2: ");
	    			$logger->info(json_encode($e->getMessage()));
	    			$maxTryValue = 0;
	    		}
	    		if($maxTryValue < 4) {
	    			$this->configWriter->save('ordersync/cron/processlock_maxtry', $maxTryValue + 1);	    			
	    		} else {
	    			$this->configWriter->save('ordersync/cron/processlock', 0);
	    			$this->configWriter->save('ordersync/cron/processlock_maxtry', 0);	    			
	    		}
	    	}
    	} catch(\Exception $e) {
    		$logger->info("Catch3: ");
    		$logger->info(json_encode($e->getMessage()));
    	}
    }
}

