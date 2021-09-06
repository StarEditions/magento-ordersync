<?php

namespace Letsprintondemand\OrderSync\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * EAV setup factory
     *
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $eavSetupFactory;

    protected $configWriter;

    /**
     * Constructor
     *
     * @param CategorySetupFactory $categorySetupFactory
     * @param EavSetupFactory $eavSetupFactory
     * @param Product $product
     * @param State $state
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        WriterInterface $configWriter
    ) {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->configWriter = $configWriter;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try{        	
        	$this->configWriter->save('ordersync/cron/processlock', 0);
            $this->configWriter->save('ordersync/cron/processlock_maxtry', 0);
        } catch (\Exception $e) {
        	//do nothing
        }
    }
}
