# Mage2 Module StarEditions OrderSync

    ``stareditions/module-ordersync``

 - [Main Functionalities](#markdown-header-main-functionalities)
 - [Installation](#markdown-header-installation)
 - [Configuration](#markdown-header-configuration)
 - [Specifications](#markdown-header-specifications)
 - [Attributes](#markdown-header-attributes)


## Main Functionalities


## Installation
\* = in production please use the `--keep-generated` option

[Installation Guide](star-editions_magento2_installation-guide.pdf)

### Type 1: Zip file

 - Unzip the zip file in `app/code/StarEditions/OrderSync`
 - Enable the module by running `php bin/magento module:enable StarEditions_OrderSync`
 - Apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`

### Type 2: Composer

 - Make the module available in a composer repository for example:
    - private repository `repo.magento.com`
    - public repository `packagist.org`
    - public github repository as vcs
 - Add the composer repository to the configuration by running `composer config repositories.repo.magento.com composer https://repo.magento.com/`
 - Install the module composer by running `composer require stareditions/module-ordersync`
 - enable the module by running `php bin/magento module:enable StarEditions_OrderSync`
 - apply database updates by running `php bin/magento setup:upgrade`\*
 - Flush the cache by running `php bin/magento cache:flush`


## Configuration




## Specifications

 - Observer
	- checkout_onepage_controller_success_action > StarEditions\OrderSync\Observer\Checkout\OnepageControllerSuccessAction


## Attributes



