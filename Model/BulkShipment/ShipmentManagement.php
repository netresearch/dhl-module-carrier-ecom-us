<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkShipment;

use Dhl\EcomUs\Model\Pipeline\ApiGateway;
use Dhl\EcomUs\Model\Pipeline\ApiGatewayFactory;
use Dhl\EcomUs\Model\ResourceModel\Package\CollectionFactory;
use Magento\Sales\Api\Data\ShipmentTrackInterface;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\BulkShipment\BulkLabelCancellationInterface;
use Netresearch\ShippingCore\Api\BulkShipment\BulkLabelCreationInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackRequest\TrackRequestInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackErrorResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackErrorResponseInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\TrackResponse\TrackResponseInterfaceFactory;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentResponseProcessorInterface;
use Netresearch\ShippingCore\Api\Pipeline\TrackResponseProcessorInterface;
use Netresearch\ShippingDispatch\Api\Data\DispatchInterface;

/**
 * Class ShipmentManagement
 *
 * Central entrypoint for creating and deleting labels.
 */
class ShipmentManagement implements BulkLabelCreationInterface, BulkLabelCancellationInterface
{
    /**
     * @var ApiGatewayFactory
     */
    private $apiGatewayFactory;

    /**
     * @var ShipmentResponseProcessorInterface
     */
    private $createResponseProcessor;

    /**
     * @var TrackResponseProcessorInterface
     */
    private $deleteResponseProcessor;

    /**
     * @var CollectionFactory;
     */
    private $packageCollectionFactory;

    /**
     * @var TrackResponseInterfaceFactory
     */
    private $successResponseFactory;

    /**
     * @var TrackErrorResponseInterfaceFactory
     */
    private $errorResponseFactory;

    /**
     * @var ApiGateway[]
     */
    private $apiGateways;

    public function __construct(
        ApiGatewayFactory $apiGatewayFactory,
        ShipmentResponseProcessorInterface $createResponseProcessor,
        TrackResponseProcessorInterface $deleteResponseProcessor,
        CollectionFactory $packageCollectionFactory,
        TrackResponseInterfaceFactory $successResponseFactory,
        TrackErrorResponseInterfaceFactory $errorResponseFactory
    ) {
        $this->apiGatewayFactory = $apiGatewayFactory;
        $this->createResponseProcessor = $createResponseProcessor;
        $this->deleteResponseProcessor = $deleteResponseProcessor;
        $this->packageCollectionFactory = $packageCollectionFactory;
        $this->successResponseFactory = $successResponseFactory;
        $this->errorResponseFactory = $errorResponseFactory;
    }

    /**
     * Create api gateway.
     *
     * API gateways are created with store specific configuration and configured post-processors (bulk or popup).
     *
     * @param int $storeId
     * @return ApiGateway
     */
    private function getApiGateway(int $storeId): ApiGateway
    {
        if (!isset($this->apiGateways[$storeId])) {
            $api = $this->apiGatewayFactory->create(
                [
                    'storeId' => $storeId,
                    'responseProcessor' => $this->createResponseProcessor,
                ]
            );

            $this->apiGateways[$storeId] = $api;
        }

        return $this->apiGateways[$storeId];
    }

    /**
     * Create shipment labels at the DHL eCom US API
     *
     * Shipment requests are divided by store for multi-store support (different DHL account configurations).
     *
     * @param Request[] $shipmentRequests
     * @return ShipmentResponseInterface[]
     */
    public function createLabels(array $shipmentRequests): array
    {
        if (empty($shipmentRequests)) {
            return [];
        }

        $apiRequests = [];
        $apiResults = [];

        foreach ($shipmentRequests as $shipmentRequest) {
            $storeId = (int)$shipmentRequest->getOrderShipment()->getStoreId();
            $apiRequests[$storeId][] = $shipmentRequest;
        }

        foreach ($apiRequests as $storeId => $storeApiRequests) {
            $api = $this->getApiGateway($storeId);
            $apiResults[$storeId] = $api->createShipments($storeApiRequests);
        }

        if (!empty($apiResults)) {
            // convert results per store to flat response
            $apiResults = array_reduce($apiResults, 'array_merge', []);
        }

        return $apiResults;
    }

    /**
     * Cancel shipping labels.
     *
     * Cancelling labels is not actually supported at the DHL eCom US API.
     * Stale packages will be automatically purged in the DHL eCom systems.
     * However, if a package was already manifested, it must no longer be removed.
     *
     * If a package was not assigned to a dispatch or the dispatch is still
     * in "pending" state, then we return a positive response to let the caller
     * proceed. Otherwise we return an error response to prevent manifested
     * tracking numbers being deleted from the Magento instance.
     *
     * @param TrackRequestInterface[] $cancelRequests
     * @return TrackResponseInterface[]
     */
    public function cancelLabels(array $cancelRequests): array
    {
        if (empty($cancelRequests)) {
            return [];
        }

        $successResponses = [];
        $errorResponses = [];

        $trackIds = array_map(
            static function (TrackRequestInterface $cancelRequest) {
                return $cancelRequest->getSalesTrack() ? $cancelRequest->getSalesTrack()->getEntityId() : null;
            },
            $cancelRequests
        );
        $trackIds = array_filter($trackIds);

        $collection = $this->packageCollectionFactory->create();
        $collection->joinDispatch();
        $collection->addFieldToFilter(ShipmentTrackInterface::ENTITY_ID, ['in' => $trackIds]);
        $collection->addFieldToFilter(DispatchInterface::STATUS, [
            ['in' => [DispatchInterface::STATUS_PENDING, DispatchInterface::STATUS_FAILED]],
            ['null' => true],
        ]);

        // shipment tracks, indexed by track id
        $tracks = $collection->getItems();

        foreach ($cancelRequests as $shipmentNumber => $cancelRequest) {
            $track = $cancelRequest->getSalesTrack();
            if ($track && isset($tracks[$track->getEntityId()])) {
                // package is not yet manifested, can be removed.
                $responseData = [
                    TrackResponseInterface::TRACK_NUMBER => $track->getTrackNumber(),
                    TrackResponseInterface::SALES_SHIPMENT => $cancelRequest->getSalesShipment(),
                    TrackResponseInterface::SALES_TRACK => $track
                ];
                $successResponses[] = $this->successResponseFactory->create(['data' => $responseData]);
            } else {
                // package is already manifested, must not be removed.
                $errorMessage = __('Dispatched package %1 cannot be cancelled.', $track->getTrackNumber());
                $responseData = [
                    TrackErrorResponseInterface::TRACK_NUMBER => $track->getTrackNumber(),
                    TrackErrorResponseInterface::ERRORS => $errorMessage,
                    TrackErrorResponseInterface::SALES_SHIPMENT => $cancelRequest->getSalesShipment(),
                    TrackErrorResponseInterface::SALES_TRACK => $track
                ];
                $errorResponses[] = $this->errorResponseFactory->create(['data' => $responseData]);
            }
        }

        $this->deleteResponseProcessor->processResponse($successResponses, $errorResponses);

        return array_merge($successResponses, $errorResponses);
    }
}
