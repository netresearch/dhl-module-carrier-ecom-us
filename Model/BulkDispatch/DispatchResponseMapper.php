<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\EcomUs\Model\Package;
use Dhl\Sdk\EcomUs\Api\Data\Manifest\DocumentInterface;
use Dhl\Sdk\EcomUs\Api\Data\Manifest\ErrorInterface;
use Dhl\Sdk\EcomUs\Api\Data\ManifestInterface;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Sales\Model\Order\Shipment\Track;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\DispatchErrorResponseInterface;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\DispatchErrorResponseInterfaceFactory;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\DispatchSuccessResponseInterface;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\DispatchSuccessResponseInterfaceFactory;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\DocumentInterfaceFactory;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\PackageErrorInterfaceFactory;

/**
 * Create result from API response.
 */
class DispatchResponseMapper
{
    /**
     * @var DocumentInterfaceFactory
     */
    private $documentFactory;

    /**
     * @var PackageErrorInterfaceFactory
     */
    private $packageErrorFactory;

    /**
     * @var DispatchSuccessResponseInterfaceFactory
     */
    private $dispatchSuccessResponseFactory;

    /**
     * @var DispatchErrorResponseInterfaceFactory
     */
    private $dispatchErrorResponseFactory;

    public function __construct(
        DocumentInterfaceFactory $documentFactory,
        PackageErrorInterfaceFactory $packageErrorFactory,
        DispatchSuccessResponseInterfaceFactory $dispatchSuccessResponseFactory,
        DispatchErrorResponseInterfaceFactory $dispatchErrorResponseFactory
    ) {
        $this->documentFactory = $documentFactory;
        $this->packageErrorFactory = $packageErrorFactory;
        $this->dispatchSuccessResponseFactory = $dispatchSuccessResponseFactory;
        $this->dispatchErrorResponseFactory = $dispatchErrorResponseFactory;
    }

    /**
     * @param ShipmentTrackInterface[] $tracks
     * @param string $packageNumber
     * @return int
     */
    private function getTrackId(array $tracks, string $packageNumber): ?int
    {
        /** @var Track $track */
        foreach ($tracks as $track) {
            if ($track->getData(Package::DHL_PACKAGE_ID) === $packageNumber) {
                return (int) $track->getEntityId();
            }
        }

        return null;
    }

    public function createSuccessResponse(
        string $requestIndex,
        DispatchRequest $request,
        ManifestInterface $response
    ): DispatchSuccessResponseInterface {
        // only pass back documents if manifestation was completed
        $apiDocs = ($response->getStatus() === ManifestInterface::STATUS_COMPLETED) ? $response->getDocuments() : [];

        // map response for succeeded tracks/package ids
        $documents = array_map(
            function (DocumentInterface $apiDoc) {
                return $this->documentFactory->create(
                    [
                        'name' => 'Driver’s Summary Manifest',
                        'content' => $apiDoc->getData(),
                        'format' => $apiDoc->getFormat(),
                    ]
                );
            },
            $apiDocs
        );

        // map response for failed tracks/package ids
        $dispatchErrors = array_map(
            function (ErrorInterface $apiError) use ($request) {
                return $this->packageErrorFactory->create([
                    'trackId' => $this->getTrackId($request->getTracks(), $apiError->getPackageId()),
                    'errorMessage' => $apiError->getDescription(),
                ]);
            },
            $response->getErrors()
        );

        return $this->dispatchSuccessResponseFactory->create(
            [
                'requestIndex' => $requestIndex,
                'dispatchNumber' => $response->getRequestId(),
                'dispatchDate' => $response->getTimestamp(),
                'dispatchDocuments' => $documents,
                'dispatchErrors' => $dispatchErrors,
                'dispatch' => $request->getDispatch(),
                'tracks' => $request->getTracks(),
            ]
        );
    }

    public function createErrorResponse(
        string $requestIndex,
        DispatchRequest $request,
        string $errorMessage
    ): DispatchErrorResponseInterface {
        return $this->dispatchErrorResponseFactory->create(
            [
                'requestIndex' => $requestIndex,
                'error' => __('An error occurred while manifesting the dispatch: %1', $errorMessage),
                'dispatch' => $request->getDispatch(),
                'tracks' => $request->getTracks(),
            ]
        );
    }
}
