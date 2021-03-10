<?php

namespace Letsprintondemand\OrderSync\Model\Config\Source;

class ConfigOption implements \Magento\Framework\Option\ArrayInterface
{
    protected $eavConfig;

    public function __construct(
        \Magento\Eav\Model\Config $eavConfig
    ){
        $this->eavConfig = $eavConfig;
    }

    public function toOptionArray()
    {
        return $this->getBrandOptions();
    }

    public function getBrandOptions() {
        $attribute = $this->eavConfig->getAttribute('catalog_product', 'manufacturer');
        $options = $attribute->getSource()->getAllOptions();
        $result = [];
        foreach($options as $option) {
            if($option['value']) {
                $result[] = ['value' => $option['label'], 'label' => __($option['label'])];
            }
        }
        if($result) {
            return $result;
        }
        return false;
    }
}
