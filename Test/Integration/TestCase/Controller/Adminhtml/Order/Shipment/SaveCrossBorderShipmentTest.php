<?php

/**
 * See LICENSE.md for license details.
 */

namespace Dhl\EcomUs\Test\Integration\TestCase\Controller\Adminhtml\Order\Shipment;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Dhl\EcomUs\Model\Package;
use Dhl\EcomUs\Model\Pipeline\Shipment\Stage\SendRequestStage;
use Dhl\EcomUs\Model\ResourceModel\Package as PackageResource;
use Dhl\EcomUs\Test\Integration\TestDouble\Pipeline\Shipment\Stage\SendRequestStageStub;
use Dhl\Sdk\EcomUs\Exception\ServiceException;
use Dhl\ShippingCore\Api\LabelStatus\LabelStatusManagementInterface;
use Dhl\ShippingCore\Model\LabelStatus\LabelStatusProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Collection;
use Magento\Shipping\Model\Shipment\Request;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Sales\OrderBuilder;
use TddWizard\Fixtures\Sales\OrderFixture;
use TddWizard\Fixtures\Sales\OrderFixtureRollback;

/**
 * Test basic shipment creation for US-DE route with no value-added services.
 *
 * @magentoAppArea adminhtml
 */
class SaveCrossBorderShipmentTest extends SaveShipmentTest
{
    /**
     * Create order fixture for DE recipient address with two order items.
     *
     * @throws \Exception
     */
    public static function orderFixture()
    {
        $shippingMethod = EcomUs::CARRIER_CODE . '_flatrate';
        $addressBuilder = AddressBuilder::anAddress(null, 'de_DE')->asDefaultBilling()->asDefaultShipping();

        self::$order = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withProducts(
                ProductBuilder::aSimpleProduct()->withWeight(0.65)->withSku('foo'),
                ProductBuilder::aSimpleProduct()->withWeight(0.99)->withSku('bar')
            )
            ->withCustomer(CustomerBuilder::aCustomer()->withAddresses($addressBuilder))
            ->build();
    }

    /**
     * Roll back fixture.
     */
    public static function orderFixtureRollback()
    {
        try {
            OrderFixtureRollback::create()->execute(new OrderFixture(self::$order));
        } catch (\Exception $exception) {
            $argv = $_SERVER['argv'] ?? [];
            if (in_array('--verbose', $argv, true)) {
                $message = sprintf("Error during rollback: %s%s", $exception->getMessage(), PHP_EOL);
                register_shutdown_function('fwrite', STDERR, $message);
            }
        }
    }

    /**
     * Scenario: Two products are contained in an order, both are valid.
     *
     * - Assert that one shipment is created
     * - Assert that one tracking number is created per package
     * - Assert that label status is set to "Processed"
     *
     * @test
     * @dataProvider packagingDataProviderCrossBorder
     * @magentoDataFixture orderFixture
     *
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
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/pickup_account_number 123456
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/distribution_center FOO1
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/checkout_settings/emulated_carrier flatrate
     *
     * @magentoConfigFixture current_store carriers/flatrate/type O
     * @magentoConfigFixture current_store carriers/flatrate/handling_type F
     * @magentoConfigFixture current_store carriers/flatrate/price 5.00
     *
     * @param callable $getData
     * @throws LocalizedException
     */
    public function saveShipment(callable $getData)
    {
        // create packaging data from order fixture
        $data = $getData();

        // dispatch
        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue('data', \json_encode($data));
        $this->getRequest()->setParam('order_id', self::$order->getEntityId());
        $this->dispatch($this->uri);

        /** @var Collection $shipmentCollection */
        $shipmentCollection = $this->_objectManager->create(Collection::class);
        $shipments = $shipmentCollection->setOrderFilter(self::$order)->getItems();
        $shipments = array_values($shipments);

        // assert that exactly one shipment was created for the order
        self::assertCount(1, $shipments);
        $shipment = $shipments[0];

        // assert shipping label was persisted with shipment
        self::assertStringStartsWith('%PDF-1', $shipment->getShippingLabel());

        // assert that one track was created per package
        $tracks = $shipment->getTracks();
        self::assertCount(count($data['packages']), $tracks);

        //assert that package was saved with track id
        /** @var ShipmentTrackInterface $track */
        $track = current($shipments[0]->getTracks());
        /** @var PackageResource $packageResource */
        $packageResource = $this->_objectManager->create(PackageResource::class);
        /** @var Package $package */
        $package = $this->_objectManager->create(Package::class);
        $packageResource->load($package, $track->getEntityId(), Package::TRACK_ID);
        self::assertNotNull($package->getPackageId());
        self::assertNotNull($package->getDhlPackageId());

        // assert that the order's label status is "Processed"
        /** @var LabelStatusProvider $labelStatusProvider */
        $labelStatusProvider = $this->_objectManager->create(LabelStatusProvider::class);
        $labelStatus = $labelStatusProvider->getLabelStatus([self::$order->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_PROCESSED,
            $labelStatus[self::$order->getEntityId()]
        );

        // assert that cross-border properties of last package were sent to web service (other api requests are lost).
        /** @var SendRequestStageStub $pipelineStage */
        $pipelineStage = $this->_objectManager->get(SendRequestStage::class);

        $package = array_pop($data['packages']);
        $apiPayload = json_encode($pipelineStage->apiRequests[0]);

        $dgCat = $package['package']['packageCustoms']['dgCategory'];
        self::assertContains("\"contentCategory\":\"$dgCat\"", $apiPayload);

        foreach ($package['items'] as $packageItem) {
            $hsCode = $packageItem['itemCustoms']['hsCode'];
            self::assertContains("\"hsCode\":\"$hsCode\"", $apiPayload);

            $origin = $packageItem['itemCustoms']['countryOfOrigin'];
            self::assertContains("\"countryOfOrigin\":\"$origin\"", $apiPayload);

            $desc = $packageItem['itemCustoms']['exportDescription'];
            self::assertContains("\"itemDescription\":\"$desc\"", $apiPayload);
        }
    }

    /**
     * Scenario: Two products are contained in an order, web service request fails for ALL of them.
     *
     * - Assert that no shipment is created
     * - Assert that label status is set to "Failed"
     *
     * @test
     * @dataProvider packagingDataProviderCrossBorder
     * @magentoDataFixture orderFixture
     *
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
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/pickup_account_number 123456
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/distribution_center FOO1
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/checkout_settings/emulated_carrier flatrate
     *
     * @magentoConfigFixture current_store carriers/flatrate/type O
     * @magentoConfigFixture current_store carriers/flatrate/handling_type F
     * @magentoConfigFixture current_store carriers/flatrate/price 5.00
     *
     * @param callable $getData
     * @throws LocalizedException
     */
    public function apiRequestsFail(callable $getData)
    {
        // create packaging data from order fixture
        $packages = $getData();

        /** @var SendRequestStageStub $pipelineStage */
        $pipelineStage = $this->_objectManager->get(SendRequestStage::class);
        $pipelineStage->responseCallback = function () {
            return new ServiceException('uh-oh…');
        };

        // dispatch
        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue('data', \json_encode($packages));
        $this->getRequest()->setParam('order_id', self::$order->getEntityId());
        $this->dispatch($this->uri);

        /** @var Collection $shipmentCollection */
        $shipmentCollection = $this->_objectManager->create(Collection::class);
        $shipments = $shipmentCollection->setOrderFilter(self::$order)->getItems();

        self::assertCount(0, $shipments);

        // assert that the order's label status is "Failed"
        /** @var LabelStatusProvider $labelStatusProvider */
        $labelStatusProvider = $this->_objectManager->create(LabelStatusProvider::class);
        $labelStatus = $labelStatusProvider->getLabelStatus([self::$order->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_FAILED,
            $labelStatus[self::$order->getEntityId()]
        );
    }

    /**
     * Scenario: Two products are contained in an order, web service request fails for ONE of them.
     *
     * - Assert that no shipment is created
     * - Assert that label status is set to "Failed"
     *
     * @test
     * @dataProvider packagingDataProviderCrossBorder
     * @magentoDataFixture orderFixture
     *
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
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/pickup_account_number 123456
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/account_settings/distribution_center FOO1
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlecomus/checkout_settings/emulated_carrier flatrate
     *
     * @magentoConfigFixture current_store carriers/flatrate/type O
     * @magentoConfigFixture current_store carriers/flatrate/handling_type F
     * @magentoConfigFixture current_store carriers/flatrate/price 5.00
     *
     * @param callable $getData
     * @throws LocalizedException
     */
    public function apiRequestFailsPartially(callable $getData)
    {
        // create packaging data from order fixture
        $packages = $getData();

        /** @var SendRequestStageStub $pipelineStage */
        $pipelineStage = $this->_objectManager->get(SendRequestStage::class);
        $pipelineStage->responseCallback = function (Request $shipmentRequest) {
            $orderItemId = null;
            foreach ($shipmentRequest->getOrderShipment()->getItems() as $shipmentItem) {
                if ($shipmentItem->getSku() === 'bar') {
                    $orderItemId = (int) $shipmentItem->getOrderItemId();
                }
            }

            if (in_array($orderItemId, array_keys($shipmentRequest->getData('package_items')), true)) {
                // if the current package includes the item "bar", then throw exception. otherwise proceed.
                return new ServiceException('uh-oh…');
            }

            return null;
        };

        // dispatch
        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue('data', \json_encode($packages));
        $this->getRequest()->setParam('order_id', self::$order->getEntityId());
        $this->dispatch($this->uri);

        /** @var Collection $shipmentCollection */
        $shipmentCollection = $this->_objectManager->create(Collection::class);
        $shipments = $shipmentCollection->setOrderFilter(self::$order)->getItems();

        self::assertCount(0, $shipments);

        // assert that the order's label status is "Failed"
        /** @var LabelStatusProvider $labelStatusProvider */
        $labelStatusProvider = $this->_objectManager->create(LabelStatusProvider::class);
        $labelStatus = $labelStatusProvider->getLabelStatus([self::$order->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_FAILED,
            $labelStatus[self::$order->getEntityId()]
        );
    }
}
