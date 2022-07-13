<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace StarEditions\OrderSync\Controller\Adminhtml\Update;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use StarEditions\OrderSync\Helper\Data;

class Endpoint extends Action
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Endpoint constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        Data $helper,
        LoggerInterface $logger
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
     * @return ResultInterface
     */
    public function execute()
    {
        try {
            $data = $this->getRequest()->getPostValue();
            if($data['form_key'] && $data['valid'] && $data['endpoint_url']) {
                $apiUrl = $data['apiUrl'];
                $storename = $data['store_name'];
                $token = $data['token'];

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
        } catch (LocalizedException $e) {
            return $this->jsonResponse(false);
        } catch (Exception $e) {
            $this->logger->critical($e);
            return $this->jsonResponse(false);
        }
    }

    /**
     * Create json response
     *
     * @return ResultInterface
     */
    public function jsonResponse($response = '')
    {
        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($response)
        );
    }
}

