<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\TestCase\Controller\Adminhtml\Shipment;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Dhl\EcomUs\Model\Package;
use Dhl\EcomUs\Model\Pipeline\Shipment\Stage\SendRequestStage;
use Dhl\EcomUs\Model\ResourceModel\Package as PackageResource;
use Dhl\EcomUs\Test\Integration\TestDouble\Pipeline\Shipment\Stage\SendRequestStageStub;
use Dhl\Sdk\EcomUs\Exception\ServiceException;
use Dhl\ShippingCore\Api\LabelStatus\LabelStatusManagementInterface;
use Dhl\ShippingCore\Model\LabelStatus\LabelStatusProvider;
use Dhl\ShippingCore\Test\Integration\Fixture\OrderBuilder;
use Magento\Customer\Model\Session;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Model\Order;
use Magento\Shipping\Model\Shipment\Request;
use Magento\TestFramework\Helper\Bootstrap;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Sales\InvoiceBuilder;
use TddWizard\Fixtures\Sales\OrderFixture;
use TddWizard\Fixtures\Sales\OrderFixtureRollback;
use TddWizard\Fixtures\Sales\ShipmentBuilder;

/**
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class AutoCreateUsTest extends AutoCreateTest
{
    /**
     * @var OrderInterface[]|Order[]
     */
    private static $orders;

    /**
     * Create order fixtures for US recipient address.
     *
     * @fixme(nr): customer must be logged out manually until PR #41 is merged and released.
     * @link https://github.com/tddwizard/magento2-fixtures/pull/41
     *
     * @throws \Exception
     */
    public static function createOrders()
    {
        $shippingMethod = EcomUs::CARRIER_CODE . '_flatrate';
        $addressBuilder = AddressBuilder::anAddress('en_US')->asDefaultBilling()->asDefaultShipping();
        $customerBuilder = CustomerBuilder::aCustomer()->withAddresses($addressBuilder);

        // order with shipment, track/label, label status processed
        $completeOrder = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withCustomer($customerBuilder)
            ->withLabelStatus(LabelStatusManagementInterface::LABEL_STATUS_PROCESSED)
            ->withProducts(ProductBuilder::aSimpleProduct()->withWeight(0.5))
            ->build();
        InvoiceBuilder::forOrder($completeOrder)->build();
        ShipmentBuilder::forOrder($completeOrder)->withTrackingNumbers('123456')->build();
        Bootstrap::getObjectManager()->get(Session::class)->logout();

        // order with shipment, no track/label, label status pending
        $processingPendingOrder = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withCustomer($customerBuilder)
            ->withProducts(ProductBuilder::aSimpleProduct()->withWeight(0.6))
            ->build();
        ShipmentBuilder::forOrder($processingPendingOrder)->build();
        Bootstrap::getObjectManager()->get(Session::class)->logout();

        // order with shipment, no track/label, label status failed
        $processingFailedOrder = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withCustomer($customerBuilder)
            ->withLabelStatus(LabelStatusManagementInterface::LABEL_STATUS_FAILED)
            ->withProducts(ProductBuilder::aSimpleProduct()->withWeight(0.7))
            ->build();
        ShipmentBuilder::forOrder($processingFailedOrder)->build();
        Bootstrap::getObjectManager()->get(Session::class)->logout();

        // order with shipment, one track/label, label status partial
        $processingPartialOrder = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withCustomer($customerBuilder)
            ->withLabelStatus(LabelStatusManagementInterface::LABEL_STATUS_PARTIAL)
            ->withProducts(
                ProductBuilder::aSimpleProduct()->withSku('foo')->withWeight(0.8),
                ProductBuilder::aSimpleProduct()->withSku('bar')->withWeight(0.9)
            )->withCart(
                CartBuilder::forCurrentSession()
                   ->withSimpleProduct('foo', 2)
                   ->withSimpleProduct('bar', 3)
            )->build();

        $orderItemIds = [];
        /** @var OrderItemInterface $orderItem */
        foreach ($processingPartialOrder->getAllVisibleItems() as $orderItem) {
            $orderItemIds[$orderItem->getSku()] = (int) $orderItem->getItemId();
        }

        ShipmentBuilder::forOrder($processingPartialOrder)
            ->withItem($orderItemIds['foo'], 2)
            ->withItem($orderItemIds['bar'], 2)
            ->withTrackingNumbers('234567')
            ->build();
        Bootstrap::getObjectManager()->get(Session::class)->logout();

        // order with no shipment, to be selected for mass action
        $pendingOrderSelected = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withCustomer($customerBuilder)
            ->withProducts(ProductBuilder::aSimpleProduct()->withWeight(1.0))
            ->build();
        Bootstrap::getObjectManager()->get(Session::class)->logout();

        // order with no shipment
        $pendingOrder = OrderBuilder::anOrder()
            ->withShippingMethod($shippingMethod)
            ->withCustomer($customerBuilder)
            ->withProducts(ProductBuilder::aSimpleProduct()->withWeight(1.1))
            ->build();
        Bootstrap::getObjectManager()->get(Session::class)->logout();

        self::$orders = [
            // [order_status]_[label_status] => order
            'complete_processed' => $completeOrder,
            'processing_pending' => $processingPendingOrder,
            'processing_failed' => $processingFailedOrder,
            'processing_partial' => $processingPartialOrder,
            'pending_pending_selected' => $pendingOrderSelected,
            'pending_pending' => $pendingOrder,
        ];
    }

    /**
     * @throws \Exception
     */
    public static function createOrdersRollback()
    {
        $orderFixtures = array_map(
            function (OrderInterface $order) {
                return new OrderFixture($order);
            },
            self::$orders,
            [] // get rid of string keys
        );

        try {
            OrderFixtureRollback::create()->execute(...$orderFixtures);
        } catch (\Exception $exception) {
            $argv = $_SERVER['argv'] ?? [];
            if (in_array('--verbose', $argv, true)) {
                $message = sprintf("Error during rollback: %s%s", $exception->getMessage(), PHP_EOL);
                register_shutdown_function('fwrite', STDERR, $message);
            }
        }
    }

    /**
     * Scenario: Multiple orders are selected in grid, "retry failed" is disabled.
     *
     * - Assert that only selected orders with pending label are sent to the web service.
     * - Assert that previously failed order is not sent to web service.
     * - Assert that successfully processed orders have one more shipment with label binary and track.
     * - Assert that label status are updated to the expected values.
     *
     * @test
     * @magentoDataFixture createOrders
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
    public function createLabels()
    {
        $selectedOrderIds = [
            self::$orders['complete_processed']->getId(), // not to be sent (complete)
            self::$orders['processing_pending']->getId(), // to be sent #1
            self::$orders['processing_failed']->getId(), // not to be sent (retry disabled)
            self::$orders['processing_partial']->getId(), // to be sent #2
            self::$orders['pending_pending_selected']->getId(), // to be sent #3 – will fail
        ];

        /** @var SendRequestStageStub $pipelineStage */
        $pipelineStage = $this->_objectManager->get(SendRequestStage::class);
        $pipelineStage->responseCallback = function (Request $shipmentRequest) {
            $shipment = $shipmentRequest->getOrderShipment();
            if ($shipment->getOrderId() === self::$orders['pending_pending_selected']->getId()) {
                return new ServiceException('uh-oh…');
            }

            return null;
        };

        // prepare mass action post data from order fixtures
        $postData = [
            'selected' => $selectedOrderIds,
            'namespace' => 'sales_order_grid'
        ];

        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue($postData);
        $this->dispatch($this->uri);

        /** @var SendRequestStageStub $pipelineStage */
        $pipelineStage = $this->_objectManager->get(SendRequestStage::class);

        // assert number of orders sent to the api, excluding previously failed one.
        self::assertCount(3, $pipelineStage->apiRequests);

        $shipments = self::$orders['processing_pending']->getShipmentsCollection()->getItems();
        $shipments = array_values($shipments);
        self::assertCount(1, $shipments);
        self::assertCount(1, $shipments[0]->getTracks());
        // assert shipping label was persisted with shipment
        self::assertStringStartsWith('%PDF-1', $shipments[0]->getShippingLabel());

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
        $labelStatus = $labelStatusProvider->getLabelStatus([self::$orders['processing_pending']->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_PROCESSED,
            $labelStatus[self::$orders['processing_pending']->getEntityId()]
        );

        $shipments = self::$orders['processing_partial']->getShipmentsCollection()->getItems();
        $shipments = array_values($shipments);
        self::assertCount(2, $shipments);
        self::assertCount(1, $shipments[1]->getTracks());
        // assert shipping label was persisted with shipment
        self::assertStringStartsWith('%PDF-1', $shipments[1]->getShippingLabel());

        //assert that package was saved with track id
        /** @var ShipmentTrackInterface $track */
        $track = current($shipments[1]->getTracks());
        /** @var PackageResource $packageResource */
        $packageResource = $this->_objectManager->create(PackageResource::class);
        /** @var Package $package */
        $package = $this->_objectManager->create(Package::class);
        $packageResource->load($package, $track->getEntityId(), Package::TRACK_ID);
        self::assertNotNull($package->getPackageId());
        self::assertNotNull($package->getDhlPackageId());

        // assert that the order's label status is "Processed"
        $labelStatus = $labelStatusProvider->getLabelStatus([self::$orders['processing_partial']->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_PROCESSED,
            $labelStatus[self::$orders['processing_partial']->getEntityId()]
        );

        $shipments = self::$orders['pending_pending_selected']->getShipmentsCollection()->getItems();
        $shipments = array_values($shipments);
        $tracks = $shipments[0]->getTracks();
        self::assertCount(1, $shipments);
        self::assertCount(0, $tracks);
        // assert shipping label was not persisted with shipment
        self::assertNull($shipments[0]->getShippingLabel());

        // assert that the order's label status is "Processed"
        $labelStatus = $labelStatusProvider->getLabelStatus([self::$orders['pending_pending_selected']->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_FAILED,
            $labelStatus[self::$orders['pending_pending_selected']->getEntityId()]
        );
    }

    /**
     * Scenario: Multiple orders are selected in grid, "retry failed" is enabled.
     *
     * - Assert that only selected orders with pending label are sent to the web service.
     * - Assert that previously failed order is also sent to the web service.
     * - Assert that successfully processed orders have one more shipment with label binary and track.
     * - Assert that label status are updated to the expected values.
     *
     * @test
     * @magentoDataFixture createOrders
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
     * @magentoConfigFixture default_store dhlshippingsolutions/dhlglobalwebservices/bulk_settings/retry_failed_shipments 1
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
    public function createLabelsWithRetryEnabled()
    {
        $selectedOrderIds = [
            self::$orders['complete_processed']->getId(), // not to be sent (complete)
            self::$orders['processing_pending']->getId(), // to be sent #1
            self::$orders['processing_failed']->getId(), // to be sent #2 (retry enabled)
            self::$orders['processing_partial']->getId(), // to be sent #3
            self::$orders['pending_pending_selected']->getId(), // to be sent #4 – will fail
        ];

        /** @var SendRequestStageStub $pipelineStage */
        $pipelineStage = $this->_objectManager->get(SendRequestStage::class);
        $pipelineStage->responseCallback = function (Request $shipmentRequest) {
            $shipment = $shipmentRequest->getOrderShipment();
            if ($shipment->getOrderId() === self::$orders['pending_pending_selected']->getId()) {
                return new ServiceException('uh-oh…');
            }

            return null;
        };

        // prepare mass action post data from order fixtures
        $postData = [
            'selected' => $selectedOrderIds,
            'namespace' => 'sales_order_grid'
        ];

        $this->getRequest()->setMethod($this->httpMethod);
        $this->getRequest()->setPostValue($postData);
        $this->dispatch($this->uri);

        /** @var SendRequestStageStub $pipelineStage */
        $pipelineStage = $this->_objectManager->get(SendRequestStage::class);

        self::assertCount(
            4,
            $pipelineStage->apiRequests,
            'An unexpected amount of orders was sent to the API (maybe missing the previously failed one)'
        );
        $shipments = self::$orders['processing_pending']->getShipmentsCollection()->getItems();
        $shipments = array_values($shipments);
        self::assertCount(1, $shipments);
        self::assertCount(1, $shipments[0]->getTracks());
        self::assertStringStartsWith(
            '%PDF-1',
            $shipments[0]->getShippingLabel(),
            'Shipping label was not persisted with shipment'
        );

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

        /** @var LabelStatusProvider $labelStatusProvider */
        $labelStatusProvider = $this->_objectManager->create(LabelStatusProvider::class);
        $labelStatus = $labelStatusProvider->getLabelStatus([self::$orders['processing_pending']->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_PROCESSED,
            $labelStatus[self::$orders['processing_pending']->getEntityId()],
            'Previously pending order\'s label status is not "Processed"'
        );

        $shipments = self::$orders['processing_partial']->getShipmentsCollection()->getItems();
        $shipments = array_values($shipments);
        self::assertCount(2, $shipments);
        self::assertCount(1, $shipments[1]->getTracks());
        self::assertStringStartsWith(
            '%PDF-1',
            $shipments[0]->getShippingLabel(),
            'Shipping label was not persisted with shipment'
        );

        //assert that package was saved with track id
        /** @var ShipmentTrackInterface $track */
        $track = current($shipments[1]->getTracks());
        /** @var PackageResource $packageResource */
        $packageResource = $this->_objectManager->create(PackageResource::class);
        /** @var Package $package */
        $package = $this->_objectManager->create(Package::class);
        $packageResource->load($package, $track->getEntityId(), Package::TRACK_ID);
        self::assertNotNull($package->getPackageId());
        self::assertNotNull($package->getDhlPackageId());

        $labelStatus = $labelStatusProvider->getLabelStatus([self::$orders['processing_partial']->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_PROCESSED,
            $labelStatus[self::$orders['processing_partial']->getEntityId()],
            'Previously partial order\'s label status is not "Processed"'
        );

        $shipments = self::$orders['processing_failed']->getShipmentsCollection()->getItems();
        $shipments = array_values($shipments);
        self::assertCount(1, $shipments);
        self::assertCount(1, $shipments[0]->getTracks());
        self::assertStringStartsWith(
            '%PDF-1',
            $shipments[0]->getShippingLabel(),
            'Shipping label was not persisted with shipment'
        );

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

        $labelStatus = $labelStatusProvider->getLabelStatus([self::$orders['processing_failed']->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_PROCESSED,
            $labelStatus[self::$orders['processing_failed']->getEntityId()],
            'The previously failed and retried order\'s label status is not "Processed" (retry not working?)'
        );

        $shipments = self::$orders['pending_pending_selected']->getShipmentsCollection()->getItems();
        $shipments = array_values($shipments);
        self::assertCount(1, $shipments);
        self::assertCount(0, $shipments[0]->getTracks());
        self::assertNull(
            $shipments[0]->getShippingLabel(),
            'A shipping label was persisted for a shipment expected to fail'
        );

        $labelStatus = $labelStatusProvider->getLabelStatus([self::$orders['pending_pending_selected']->getEntityId()]);
        self::assertSame(
            LabelStatusManagementInterface::LABEL_STATUS_FAILED,
            $labelStatus[self::$orders['pending_pending_selected']->getEntityId()],
            'Label status of order expected to have failed is not "failed"'
        );
    }
}
