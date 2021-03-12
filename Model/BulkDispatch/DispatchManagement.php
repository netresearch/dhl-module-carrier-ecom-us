<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkDispatch;

use Dhl\EcomUs\Model\Package;
use Dhl\EcomUs\Model\Webservice\DispatchServiceFactory;
use Dhl\Sdk\EcomUs\Api\Data\ManifestInterface;
use Dhl\Sdk\EcomUs\Api\ManifestServiceInterface;
use Dhl\Sdk\EcomUs\Exception\DetailedServiceException;
use Dhl\Sdk\EcomUs\Exception\ServiceException;
use Magento\Sales\Model\Order\Shipment\Track;
use Netresearch\ShippingDispatch\Api\BulkDispatch\DispatchManagementInterface;
use Netresearch\ShippingDispatch\Api\Data\DispatchInterface;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\CancellationErrorResponseInterfaceFactory;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\CancellationResponseInterface;
use Netresearch\ShippingDispatch\Api\Data\DispatchResponse\CancellationSuccessResponseInterfaceFactory;

class DispatchManagement implements DispatchManagementInterface
{
    /**
     * @var DispatchRequestBuilder
     */
    private $requestBuilder;

    /**
     * @var DispatchTracksLoader
     */
    private $trackLoader;

    /**
     * @var DispatchServiceFactory
     */
    private $serviceFactory;

    /**
     * @var DispatchResponseMapper
     */
    private $responseMapper;

    /**
     * @var CancellationSuccessResponseInterfaceFactory
     */
    private $cancellationSuccessResponseFactory;

    /**
     * @var CancellationErrorResponseInterfaceFactory
     */
    private $cancellationErrorResponseFactory;

    /**
     * @var ManifestServiceInterface[]
     */
    private $apiServices = [];

    public function __construct(
        DispatchRequestBuilder $requestBuilder,
        DispatchTracksLoader $trackLoader,
        DispatchServiceFactory $serviceFactory,
        DispatchResponseMapper $responseMapper,
        CancellationSuccessResponseInterfaceFactory $cancellationSuccessResponseFactory,
        CancellationErrorResponseInterfaceFactory $cancellationErrorResponseFactory
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->trackLoader = $trackLoader;
        $this->serviceFactory = $serviceFactory;
        $this->responseMapper = $responseMapper;
        $this->cancellationSuccessResponseFactory = $cancellationSuccessResponseFactory;
        $this->cancellationErrorResponseFactory = $cancellationErrorResponseFactory;
    }

    /**
     * Retrieve pre-configured manifestation service.
     *
     * @param int $storeId
     * @return ManifestServiceInterface
     */
    private function getDispatchService(int $storeId): ManifestServiceInterface
    {
        if (!isset($this->apiServices[$storeId])) {
            $dispatchService = $this->serviceFactory->create(['storeId' => $storeId]);

            $this->apiServices[$storeId] = $dispatchService;
        }

        return $this->apiServices[$storeId];
    }

    /**
     * @param DispatchRequest $dispatchRequest
     * @return ManifestInterface
     * @throws ServiceException
     * @throws DetailedServiceException
     */
    private function createManifest(DispatchRequest $dispatchRequest): ManifestInterface
    {
        $api = $this->getDispatchService($dispatchRequest->getStoreId());

        // collect Customer Confirmation Numbers (DHL Package IDs) for web service request
        $dhlPackageIds = array_map(
            function (Track $dispatchTrack) {
                return $dispatchTrack->getData(Package::DHL_PACKAGE_ID);
            },
            $dispatchRequest->getTracks()
        );

        return $api->createPackageManifest(
            $dispatchRequest->getPickupAccountNumber(),
            [],
            $dhlPackageIds
        );
    }

    /**
     * @param DispatchRequest $dispatchRequest
     * @return ManifestInterface
     * @throws DetailedServiceException
     * @throws ServiceException
     */
    private function downloadManifest(DispatchRequest $dispatchRequest): ManifestInterface
    {
        $api = $this->getDispatchService($dispatchRequest->getStoreId());
        return $api->getManifest(
            $dispatchRequest->getPickupAccountNumber(),
            $dispatchRequest->getDispatch()->getDispatchNumber()
        );
    }

    public function manifest(array $dispatches): array
    {
        $responses = [];

        $dispatchRequests = $this->requestBuilder->setDispatches($dispatches)->create();
        foreach ($dispatchRequests as $requestIndex => $dispatchRequest) {
            $status = $dispatchRequest->getDispatch()->getStatus();
            try {
                if ($status === DispatchInterface::STATUS_PENDING || $status === DispatchInterface::STATUS_FAILED) {
                    // create manifest
                    $manifest = $this->createManifest($dispatchRequest);
                } elseif ($status === DispatchInterface::STATUS_PROCESSING) {
                    // download existing manifest with documentation
                    $manifest = $this->downloadManifest($dispatchRequest);
                } else {
                    continue;
                }

                $responses[] = $this->responseMapper->createSuccessResponse(
                    (string)$requestIndex,
                    $dispatchRequest,
                    $manifest
                );
            } catch (ServiceException $exception) {
                $responses[] = $this->responseMapper->createErrorResponse(
                    (string)$requestIndex,
                    $dispatchRequest,
                    $exception->getMessage()
                );
            }
        }

        return $responses;
    }

    /**
     * Cancel dispatches.
     *
     * The DHL eCommerce Americas API does not offer manifest cancellation. However,
     * as long as the dispatch is not yet completed, it can safely be deleted.
     *
     * @param DispatchInterface[] $dispatches
     * @return CancellationResponseInterface[]
     */
    public function cancel(array $dispatches): array
    {
        $responses = [];

        $tracks = $this->trackLoader->getTracks($dispatches);
        foreach ($dispatches as $index => $dispatch) {
            if ($dispatch->getStatus() === DispatchInterface::STATUS_COMPLETE) {
                $responses[] = $this->cancellationErrorResponseFactory->create([
                    'requestIndex' => $index,
                    'error' => __('Complete dispatches cannot be cancelled.'),
                    'dispatch' => $dispatch,
                    'tracks' => $tracks[$dispatch->getEntityId()],
                ]);
            } else {
                $responses[] = $this->cancellationSuccessResponseFactory->create([
                    'requestIndex' => $index,
                    'dispatch' => $dispatch,
                    'tracks' => $tracks[$dispatch->getEntityId()] ?? [],
                ]);
            }
        }

        return $responses;
    }
}
