<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Observer;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Dhl\EcomUs\Model\Package;
use Dhl\EcomUs\Model\PackageFactory;
use Dhl\EcomUs\Model\ResourceModel\Package as PackageResource;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Psr\Log\LoggerInterface;

/**
 * Add track ID foreign key to DHL eCom package entity as soon as it becomes available.
 */
class AddTrackId implements ObserverInterface
{
    /**
     * @var PackageFactory
     */
    private $packageFactory;

    /**
     * @var PackageResource
     */
    private $resource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        PackageFactory $packageFactory,
        PackageResource $resource,
        LoggerInterface $logger
    ) {
        $this->packageFactory = $packageFactory;
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * Add Track Id to found package.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var ShipmentTrackInterface $track */
        $track = $observer->getData('track');
        if ($track->getCarrierCode() !== EcomUs::CARRIER_CODE) {
            return;
        }

        $package = $this->packageFactory->create();
        try {
            $this->resource->loadByTrackNumber($package, $track->getTrackNumber());
            $package->setData(Package::TRACK_ID, $track->getEntityId());
            $this->resource->save($package);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }
}
