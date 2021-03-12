DHL eCommerce Solutions Americas Carrier Extension
==================================================

The DHL eCommerce US extension for Magento® 2 integrates the
_DHL eCommerce Solutions Americas_ API into the order processing workflow.

Description
-----------
This extension enables merchants to request shipping labels and tracking information
for incoming orders via the [DHL eCommerce Solutions Americas API](https://api.dhlecs.com/docs).

Requirements
------------
* PHP >= 7.1.0

Compatibility
-------------
* Magento >= 2.3.0+

Installation Instructions
-------------------------

Install sources:

    composer require dhl/module-carrier-ecom-us

Enable module:

    ./bin/magento module:enable Dhl_EcomUs
    ./bin/magento setup:upgrade

Flush cache and compile:

    ./bin/magento cache:flush
    ./bin/magento setup:di:compile

Uninstallation
--------------

To unregister the carrier module from the application, run the following command:

    ./bin/magento module:uninstall --remove-data Dhl_EcomUs
    composer update

This will automatically remove source files, clean up the database, update package dependencies.

To clean up the database manually, run the following commands:

    DROP TABLE `dhlecomus_package`;
    DELETE FROM `core_config_data` WHERE `path` LIKE 'carriers/dhlecomus/%';

Support
-------
In case of questions or problems, please have a look at the
[Support Portal (FAQ)](https://dhl-ecommerce.support.netresearch.de/) first.

If the issue cannot be resolved, you can contact the support team via the
[Support Portal](https://dhl-ecommerce.support.netresearch.de/) or by sending an email
to <dhl-ecommerce.support@netresearch.de>.

License
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2021 DHL eCommerce Solutions
