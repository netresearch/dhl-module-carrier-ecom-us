<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest;

use Magento\Framework\DataObjectFactory;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestModifier\PackagingOptionReaderInterfaceFactory;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestModifierInterface;

/**
 * Class RequestModifier
 *
 * Add defaults to shipment request in bulk actions with no user input.
 */
class RequestModifier implements RequestModifierInterface
{
    /**
     * @var RequestModifierInterface
     */
    private $coreModifier;

    /**
     * @var PackagingOptionReaderInterfaceFactory
     */
    private $packagingOptionReaderFactory;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    public function __construct(
        RequestModifierInterface $coreModifier,
        PackagingOptionReaderInterfaceFactory $packagingOptionReaderFactory,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->coreModifier = $coreModifier;
        $this->packagingOptionReaderFactory = $packagingOptionReaderFactory;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Modify shipper parameters.
     *
     * DHL eCom US requires a contact person name for cross-border shipments.
     * In manual mode, the name of the currently logged in admin panel user is used.
     * In cron mode, there is no logged-in user. We set the store name for now
     * until a dedicated config setting is introduced.
     *
     * @see \Magento\Shipping\Model\Shipping\Labels::setShipperDetails
     *
     * @param Request $shipmentRequest
     */
    private function modifyShipper(Request $shipmentRequest)
    {
        $shipmentRequest->setShipperContactPersonName($shipmentRequest->getShipperContactCompanyName());
    }
    /**
     * Add default shipping product to package params, e.g. EXP or PLT
     *
     * @param Request $shipmentRequest
     * @return void
     */
    private function modifyPackage(Request $shipmentRequest): void
    {
        $reader = $this->packagingOptionReaderFactory->create(['shipment' => $shipmentRequest->getOrderShipment()]);

        $packages = [];
        foreach ($shipmentRequest->getData('packages') as $packageId => $package) {
            $package['params']['description'] = $reader->getPackageOptionValue('packageDetails', 'description');
            $packages[$packageId] = $package;
        }

        // set all updated packages to request
        $shipmentRequest->setData('packages', $packages);

        // add current package's params to request (compare AbstractCarrierOnline::requestToShipment)
        $package = $packages[$shipmentRequest->getData('package_id')];
        $shipmentRequest->setData('package_params', $this->dataObjectFactory->create(['data' => $package['params']]));
    }

    /**
     * Add shipment request data using given shipment.
     *
     * The request modifier collects all additional data from defaults (config, product attributes)
     * during bulk label creation where no user input (packaging popup) is involved.
     *
     * @param Request $shipmentRequest
     * @return void
     */
    public function modify(Request $shipmentRequest): void
    {
        // add carrier-agnostic data
        $this->coreModifier->modify($shipmentRequest);

        // add carrier-specific data
        $this->modifyShipper($shipmentRequest);
        $this->modifyPackage($shipmentRequest);
    }
}
