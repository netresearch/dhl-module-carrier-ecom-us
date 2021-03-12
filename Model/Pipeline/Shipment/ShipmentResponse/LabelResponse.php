<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentResponse;

use Netresearch\ShippingCore\Model\Pipeline\Shipment\ShipmentResponse\LabelResponse as CoreLabelResponse;

/**
 * The response type consumed by the core carrier to persist label binary and tracking number.
 */
class LabelResponse extends CoreLabelResponse
{
    public const TRACKING_ID = 'tracking_id';
    public const PACKAGE_ID = 'package_id';
    public const DHL_PACKAGE_ID = 'dhl_package_id';

    /**
     * Get tracking id from response
     *
     * @return string
     */
    public function getTrackingId(): string
    {
        return $this->getData(self::TRACKING_ID);
    }

    /**
     * Get package id from response.
     *
     * @return string
     */
    public function getPackageId(): string
    {
        return $this->getData(self::PACKAGE_ID);
    }

    /**
     * Get DHL package id rom response.
     *
     * @return string
     */
    public function getDhlPackageId(): string
    {
        return $this->getData(self::DHL_PACKAGE_ID);
    }
}
