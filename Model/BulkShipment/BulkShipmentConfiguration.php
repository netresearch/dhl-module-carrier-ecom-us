<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkShipment;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Dhl\EcomUs\Model\Pipeline\CreateShipments\ShipmentRequest\RequestModifier;
use Dhl\ShippingCore\Api\BulkShipment\BulkLabelCancellationInterface;
use Dhl\ShippingCore\Api\BulkShipment\BulkLabelCreationInterface;
use Dhl\ShippingCore\Api\BulkShipment\BulkShipmentConfigurationInterface;
use Dhl\ShippingCore\Api\Pipeline\ShipmentRequest\RequestModifierInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class BulkShipmentConfiguration
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link   https://www.netresearch.de/
 */
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

    /**
     * BulkShipmentConfiguration constructor.
     *
     * @param RequestModifier $requestModifier
     * @param ShipmentManagement $shipmentManagement
     */
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
        throw new LocalizedException(__('Label cancellation is not supported.'));
    }

    public function isSingleTrackDeletionAllowed(): bool
    {
        return false;
    }
}
