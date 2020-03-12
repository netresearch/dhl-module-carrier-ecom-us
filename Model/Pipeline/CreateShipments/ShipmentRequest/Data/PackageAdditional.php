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
    private $dgCategory;

    /**
     * PackageAdditional constructor.
     *
     * @param string $dgCategory
     */
    public function __construct(string $dgCategory = '')
    {
        $this->dgCategory = $dgCategory;
    }

    /**
     * @return string
     */
    public function getDgCategory(): string
    {
        return $this->dgCategory;
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
