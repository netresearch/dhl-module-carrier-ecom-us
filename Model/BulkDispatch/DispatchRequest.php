<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\Dispatches\Api\Data\DispatchInterface;
use Dhl\Dispatches\Model\Dispatch;
use Magento\Sales\Api\Data\ShipmentTrackInterface;

/**
 * Prepare data for SDK.
 */
class DispatchRequest
{
    /**
     * @var int
     */
    private $storeId;

    /**
     * @var string
     */
    private $pickupAccountNumber;

    /**
     * @var DispatchInterface
     */
    private $dispatch;

    /**
     * @var ShipmentTrackInterface[]
     */
    private $tracks;

    /**
     * DispatchRequest constructor.
     *
     * @param int $storeId
     * @param string $pickupAccountNumber
     * @param DispatchInterface $dispatch
     * @param ShipmentTrackInterface[] $tracks
     */
    public function __construct(
        int $storeId,
        DispatchInterface $dispatch,
        string $pickupAccountNumber,
        array $tracks
    ) {
        $this->storeId = $storeId;
        $this->pickupAccountNumber = $pickupAccountNumber;
        $this->dispatch = $dispatch;
        $this->tracks = $tracks;
    }

    /**
     * @return int
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * @return string
     */
    public function getPickupAccountNumber(): string
    {
        return $this->pickupAccountNumber;
    }

    /**
     * @return DispatchInterface
     */
    public function getDispatch(): DispatchInterface
    {
        return $this->dispatch;
    }

    /**
     * @return ShipmentTrackInterface[]
     */
    public function getTracks(): array
    {
        return $this->tracks;
    }
}
