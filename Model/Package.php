<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Additional identifiers for DHL eCom US packages.
 */
class Package extends AbstractModel
{
    public const ENTITY_ID = 'enity_id';
    public const TRACK_ID = 'track_id';
    public const TRACKING_ID = 'tracking_id';
    public const PACKAGE_ID = 'package_id';
    public const DHL_PACKAGE_ID = 'dhl_package_id';

    /**
     * Initialize Package resource model.
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Package::class);
        parent::_construct();
    }

    /**
     * Obtain entity identifier.
     *
     * @return int
     */
    public function getEntityId(): int
    {
        return (int) $this->getData(self::ENTITY_ID);
    }

    /**
     * Obtain reference to sales shipment track.
     *
     * @return int
     */
    public function getTrackId(): int
    {
        return (int) $this->getData(self::TRACK_ID);
    }

    /**
     * Obtain DSP tracking number/delcon (domestic only).
     *
     * @return string
     */
    public function getTrackingId(): string
    {
        return $this->getData(self::TRACKING_ID);
    }

    /**
     * Obtain customer confirmation number (CCN).
     *
     * @return string
     */
    public function getPackageId(): string
    {
        return $this->getData(self::PACKAGE_ID);
    }

    /**
     * Obtain GM number/mail identifier.
     *
     * @return string
     */
    public function getDhlPackageId(): string
    {
        return $this->getData(self::DHL_PACKAGE_ID);
    }
}
