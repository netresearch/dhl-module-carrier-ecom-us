<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\TestCase\Controller\Adminhtml\Shipment;

use Dhl\EcomUs\Model\Pipeline\Shipment\Stage\SendRequestStage;
use Dhl\EcomUs\Test\Integration\TestCase\Controller\Adminhtml\ControllerTest;
use Dhl\EcomUs\Test\Integration\TestDouble\Pipeline\Shipment\Stage\SendRequestStageStub;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;

/**
 * Base controller test for the auto-create route.
 */
abstract class AutoCreateTest extends ControllerTest
{
    /**
     * The resource used to authorize action
     *
     * @var string
     */
    protected $resource = 'Magento_Sales::ship';

    /**
     * The uri at which to access the controller
     *
     * @var string
     */
    protected $uri = 'backend/dhl/shipment/autocreate';

    /**
     * The actual test to be implemented.
     */
    abstract public function createLabels();

    /**
     * Configure pipeline stage for shipment creations.
     *
     * @throws AuthenticationException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // configure web service response
        $this->_objectManager->configure(['preferences' => [SendRequestStage::class => SendRequestStageStub::class]]);
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
     * @magentoConfigFixture default_store dhlshippingsolutions/dhlglobalwebservices/bulk_settings/retry_failed_shipments 0
     *
     * @magentoConfigFixture current_store carriers/dhlecomus/active 1
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/pickup_account_number 123456
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/distribution_center FOO1
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/checkout_settings/emulated_carrier flatrate
     *
     * @magentoConfigFixture current_store carriers/flatrate/type O
     * @magentoConfigFixture current_store carriers/flatrate/handling_type F
     * @magentoConfigFixture current_store carriers/flatrate/price 5.00
     */
    public function testAclHasAccess()
    {
        $postData = [
            'selected' => ['123456789', '987654321'],
            'namespace' => 'sales_order_grid'
        ];
        $this->getRequest()->setPostValue($postData);

        parent::testAclHasAccess();
    }
}
