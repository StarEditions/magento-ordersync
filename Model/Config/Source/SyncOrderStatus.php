<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace StarEditions\OrderSync\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

class SyncOrderStatus implements ArrayInterface
{
    /**
     * @var CollectionFactory
     */
    protected $statusCollectionFactory;

    /**
     * @param CollectionFactory $statusCollectionFactory
     */
    public function __construct(
        CollectionFactory $statusCollectionFactory
    ) {
        $this->statusCollectionFactory = $statusCollectionFactory->create();
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = $this->statusCollectionFactory->toOptionArray();
        return $options;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $options = $this->statusCollectionFactory->toArray();
        return $options;
    }
}
