<?php

/**
 * See LICENSE.md for license details.
 */

namespace Dhl\EcomUs\Test\Integration\TestCase\Controller\Adminhtml\Order\Shipment;

use Dhl\EcomUs\Model\Pipeline\Shipment\Stage\SendRequestStage as CreationStage;
use Dhl\EcomUs\Test\Integration\Provider\Controller\SaveShipment\PackagingDataProvider;
use Dhl\EcomUs\Test\Integration\TestCase\Controller\Adminhtml\ControllerTest;
use Dhl\EcomUs\Test\Integration\TestDouble\Pipeline\Shipment\Stage\SendRequestStageStub as CreationStageStub;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

/**
 * Base test to build various shipment creation scenarios on.
 */
abstract class SaveShipmentTest extends ControllerTest
{
    /**
     * The resource used to authorize action
     *
     * @var string
     */
    protected $resource = 'Magento_Sales::shipment';

    /**
     * The uri at which to access the controller
     *
     * @var string
     */
    protected $uri = 'backend/nrshipping/order_shipment/save';

    /**
     * The order to create the shipment request for.
     *
     * @var OrderInterface|Order
     */
    protected static $order;

    /**
     * The actual test to be implemented.
     *
     * @param callable $getData
     */
    abstract public function saveShipment(callable $getData);

    /**
     * Configure pipeline stage for shipment creations.
     *
     * @throws AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // configure positive web service response
        $this->_objectManager->configure(
            [
                'preferences' => [
                    CreationStage::class => CreationStageStub::class
                ],
            ]
        );
    }

    public function packagingDataProviderDomestic()
    {
        return [
            'single_package_dom' => [
                function () {
                    return PackagingDataProvider::singlePackageDomestic(self::$order);
                },
            ],
            'multi_package_dom' => [
                function () {
                    return PackagingDataProvider::multiPackageDomestic(self::$order);
                },
            ]
        ];
    }

    public function packagingDataProviderCrossBorder()
    {
        return [
            'single_package_xb' => [
                function () {
                    return PackagingDataProvider::singlePackageCrossBorder(self::$order);
                },
            ],
            'multi_package_xb' => [
                function () {
                    return PackagingDataProvider::multiPackageCrossBorder(self::$order);
                },
            ]
        ];
    }

    /**
     * @magentoConfigFixture default_store general/store_information/name NR-Test-Store
     * @magentoConfigFixture default_store general/store_information/region_id 18
     * @magentoConfigFixture default_store general/store_information/phone 000
     * @magentoConfigFixture default_store general/store_information/country_id US
     * @magentoConfigFixture default_store general/store_information/postcode 33331
     * @magentoConfigFixture default_store general/store_information/city Weston
     * @magentoConfigFixture default_store general/store_information/street_line1 2700 South Commerce Parkway
     *
     * @magentoConfigFixture default_store shipping/origin/country_id US
     * @magentoConfigFixture default_store shipping/origin/region_id 18
     * @magentoConfigFixture default_store shipping/origin/postcode 33331
     * @magentoConfigFixture default_store shipping/origin/city Weston
     * @magentoConfigFixture default_store shipping/origin/street_line1 2700 South Commerce Parkway
     *
     * @magentoConfigFixture current_store carriers/dhlecomus/active 1
     * @magentoConfigFixture current_store carriers/dhlecomus/account_settings/pickup_account_number 123456
     * @magentoConfigFixture current_store carriers/dhlecomus/account_settings/distribution_center FOO1
     * @magentoConfigFixture current_store carriers/dhlecomus/checkout_settings/emulated_carrier flatrate
     *
     * @magentoConfigFixture current_store carriers/flatrate/type O
     * @magentoConfigFixture current_store carriers/flatrate/handling_type F
     * @magentoConfigFixture current_store carriers/flatrate/price 5.00
     */
    public function testAclHasAccess()
    {
        $this->getRequest()->setParam('order_id', '123456789');
        $this->getRequest()->setParam('data', '[]');

        parent::testAclHasAccess();
    }
}
