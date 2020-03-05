<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\CreateShipments\ShipmentRequest\Data;

use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageAdditionalInterface;

/**
 * Class PackageAdditional
 */
class PackageAdditional implements PackageAdditionalInterface
{
    /**
     * @var string
     */
    private $pickupAccount;

    /**
     * @var string
     */
    private $distributionCenter;

    /**
     * PackageExtension constructor.
     *
     * @param string $pickupAccount
     * @param string $distributionCenter
     */
    public function __construct(
        string $pickupAccount,
        string $distributionCenter
    ) {
        $this->pickupAccount = $pickupAccount;
        $this->distributionCenter = $distributionCenter;
    }

    /**
     * @return string
     */
    public function getPickupAccount(): string
    {
        return $this->pickupAccount;
    }

    /**
     * @return string
     */
    public function getDistributionCenter(): string
    {
        return $this->distributionCenter;
    }

    /**
     * Obtain additional eCommerce US carrier package properties.
     *
     * @return string[]
     */
    public function getData(): array
    {
        return get_object_vars($this);
    }
}
