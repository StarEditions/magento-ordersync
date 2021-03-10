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
    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
    	\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Helper\Context $context
    ) {
    	$this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function getStoreApiUrl() {
    	return $this->scopeConfig->getValue('ordersync/settings/apiurl');
    }

    public function getStoreName() {
    	return $this->scopeConfig->getValue('ordersync/settings/storename');
    }

    public function getApiToken() {
    	return $this->scopeConfig->getValue('ordersync/settings/apitoken');
    }

    public function getStoreBrandValue() {
    	$data = $this->scopeConfig->getValue('ordersync/settings/storebrands');
    	return $data;
    }
}
