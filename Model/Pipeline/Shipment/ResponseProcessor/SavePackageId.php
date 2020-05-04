<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\ResponseProcessor;

use Dhl\EcomUs\Model\Package;
use Dhl\EcomUs\Model\PackageFactory;
use Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentResponse\LabelResponse;
use Dhl\EcomUs\Model\ResourceModel\Package as PackageResource;
use Dhl\ShippingCore\Api\Pipeline\ShipmentResponseProcessorInterface;
use Psr\Log\LoggerInterface;

/**
 * Persist additional identifiers for DHL eCom US packages.
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class SavePackageId implements ShipmentResponseProcessorInterface
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

    public function processResponse(array $labelResponses, array $errorResponses)
    {
        /** @var LabelResponse $labelResponse */
        foreach ($labelResponses as $labelResponse) {
            $responseData = [
                Package::TRACKING_ID => $labelResponse->getTrackingId(),
                Package::PACKAGE_ID => $labelResponse->getPackageId(),
                Package::DHL_PACKAGE_ID => $labelResponse->getDhlPackageId(),
            ];

            try {
                $package = $this->packageFactory->create(['data' => $responseData]);
                $this->resource->save($package);
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            }
        }
    }
}
