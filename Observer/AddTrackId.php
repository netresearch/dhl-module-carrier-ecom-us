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
 * Add Track ID foreign key to DHL eCom Package entity as soon as it becomes available.
 *
 * @author Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link https://www.netresearch.de/
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

    /**
     * SavePackageId constructor.
     *
     * @param PackageFactory $packageFactory
     * @param PackageResource $resource
     * @param LoggerInterface $logger
     */
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
     */
    public function execute(Observer $observer)
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