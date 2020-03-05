<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Model\BulkShipment;

use Dhl\EcomUs\Model\Pipeline\ApiGateway;
use Dhl\EcomUs\Model\Pipeline\ApiGatewayFactory;
use Dhl\ShippingCore\Api\BulkShipment\BulkLabelCreationInterface;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentResponseInterface;
use Dhl\ShippingCore\Api\Pipeline\ShipmentResponseProcessorInterface;
use Magento\Shipping\Model\Shipment\Request;

/**
 * Class ShipmentManagement
 *
 * Central entrypoint for creating and deleting shipments.
 */
class ShipmentManagement implements BulkLabelCreationInterface
{
    /**
     * @var ApiGatewayFactory
     */
    private $apiGatewayFactory;

    /**
     * @var ShipmentResponseProcessorInterface
     */
    private $responseProcessor;

    /**
     * @var ApiGateway[]
     */
    private $apiGateways;

    /**
     * ShipmentManagement constructor.
     *
     * @param ApiGatewayFactory $apiGatewayFactory
     * @param ShipmentResponseProcessorInterface $createResponseProcessor
     */
    public function __construct(
        ApiGatewayFactory $apiGatewayFactory,
        ShipmentResponseProcessorInterface $createResponseProcessor
    ) {
        $this->apiGatewayFactory = $apiGatewayFactory;
        $this->responseProcessor = $createResponseProcessor;
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
                    'responseProcessor' => $this->responseProcessor,
                ]
            );

            $this->apiGateways[$storeId] = $api;
        }

        return $this->apiGateways[$storeId];
    }

    /**
     * Create shipment labels at DHL Paket API
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
            $storeId = (int) $shipmentRequest->getOrderShipment()->getStoreId();
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
}
