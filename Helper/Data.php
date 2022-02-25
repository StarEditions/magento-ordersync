<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Letsprintondemand\OrderSync\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
	protected $scopeConfig;
    protected $storeManager;
    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
    	\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Helper\Context $context
    ) {
    	$this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    public function getStoreApiUrl() {
    	return $this->scopeConfig->getValue(
            'ordersync/settings/apiurl',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getStoreName() {
    	return $this->scopeConfig->getValue(
            'ordersync/settings/storename',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getApiToken() {
    	return $this->scopeConfig->getValue(
            'ordersync/settings/apitoken',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    public function getStoreBrandValue() {
    	$data = $this->scopeConfig->getValue(
            'ordersync/settings/storebrands',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    	return $data;
    }
}
