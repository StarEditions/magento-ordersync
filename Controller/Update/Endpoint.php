<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Letsprintondemand\OrderSync\Controller\Update;

class Endpoint extends \Magento\Framework\App\Action\Action
{

    protected $resultPageFactory;
    protected $jsonHelper;
    protected $helper;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Letsprintondemand\OrderSync\Helper\Data $helper,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->logger = $logger;
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $data = $this->getRequest()->getPostValue();
            if($data['form_key'] && $data['valid'] && $data['endpoint_url']) {
                $apiUrl = $this->helper->getStoreApiUrl();
                $storename = $this->helper->getStoreName();
                $token = $this->helper->getApiToken();
                $post_data['Custom_Endpoint'] = $data['endpoint_url'];
                if($apiUrl && $storename && $token) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $apiUrl.$storename.'/store');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
                    $headers = array();
                    $headers[] = 'Accept: application/json';
                    $headers[] = 'Authorization: Bearer '.$token;
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    $result = curl_exec($ch);
                    $response = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if($response == 200) {
                        curl_close($ch);
                        return $this->jsonResponse(true);
                    }
                }
                return $this->jsonResponse(false);
            }
            return $this->jsonResponse(false);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {            
            return $this->jsonResponse(false);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $this->jsonResponse(false);            
        }
    }

    /**
     * Create json response
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function jsonResponse($response = '')
    {
        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($response)
        );
    }
}

