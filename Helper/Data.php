<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace StarEditions\OrderSync\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Context $context
     */
    public function __construct(
    	ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Context $context
    ) {
    	$this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getStoreApiUrl() {
    	return $this->scopeConfig->getValue(
            'ordersync/settings/apiurl',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getStoreName() {
    	return $this->scopeConfig->getValue(
            'ordersync/settings/storename',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getApiToken() {
    	return $this->scopeConfig->getValue(
            'ordersync/settings/apitoken',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getStoreBrandValue() {
    	$data = $this->scopeConfig->getValue(
            'ordersync/settings/storebrands',
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    	return $data;
    }
}
