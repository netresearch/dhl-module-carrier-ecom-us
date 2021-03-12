<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkShipment;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest\RequestModifier;
use Netresearch\ShippingCore\Api\BulkShipment\BulkLabelCancellationInterface;
use Netresearch\ShippingCore\Api\BulkShipment\BulkLabelCreationInterface;
use Netresearch\ShippingCore\Api\BulkShipment\BulkShipmentConfigurationInterface;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestModifierInterface;

class BulkShipmentConfiguration implements BulkShipmentConfigurationInterface
{
    /**
     * @var RequestModifier
     */
    private $requestModifier;

    /**
     * @var ShipmentManagement
     */
    private $shipmentManagement;

    public function __construct(
        RequestModifier $requestModifier,
        ShipmentManagement $shipmentManagement
    ) {
        $this->requestModifier = $requestModifier;
        $this->shipmentManagement = $shipmentManagement;
    }

    public function getCarrierCode(): string
    {
        return EcomUs::CARRIER_CODE;
    }

    public function getRequestModifier(): RequestModifierInterface
    {
        return $this->requestModifier;
    }

    public function getLabelService(): BulkLabelCreationInterface
    {
        return $this->shipmentManagement;
    }

    public function getCancellationService(): BulkLabelCancellationInterface
    {
        return $this->shipmentManagement;
    }

    public function isSingleTrackDeletionAllowed(): bool
    {
        return false;
    }
}
