<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest\Data;

use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageAdditionalInterface;

class PackageAdditional implements PackageAdditionalInterface
{
    /**
     * @var string
     */
    private $billingReference;

    /**
     * @var string
     */
    private $dgCategory;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $termsOfTrade;

    public function __construct(
        string $billingReference = '',
        string $dgCategory = '',
        string $description = '',
        string $termsOfTrade = ''
    ) {
        $this->billingReference = $billingReference;
        $this->dgCategory = $dgCategory;
        $this->description = $description;
        $this->termsOfTrade = $termsOfTrade;
    }

    public function getBillingReference(): string
    {
        return $this->billingReference;
    }

    public function getDgCategory(): string
    {
        return $this->dgCategory;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTermsOfTrade(): string
    {
        return $this->termsOfTrade;
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
