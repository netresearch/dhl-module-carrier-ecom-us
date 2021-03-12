<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline;

use Dhl\EcomUs\Model\Pipeline\Shipment\ArtifactsContainer;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\LabelResponseInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentErrorResponseInterface;
use Netresearch\ShippingCore\Api\Pipeline\CreateShipmentsPipelineInterface;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentResponseProcessorInterface;

/**
 * Class ApiGateway
 *
 * Magento carrier-aware wrapper around the DHL eCommerce Americas API SDK.
 */
class ApiGateway
{
    /**
     * @var CreateShipmentsPipelineInterface
     */
    private $pipeline;

    /**
     * @var ShipmentResponseProcessorInterface
     */
    private $responseProcessor;

    /**
     * @var int
     */
    private $storeId;

    public function __construct(
        CreateShipmentsPipelineInterface $pipeline,
        ShipmentResponseProcessorInterface $responseProcessor,
        int $storeId
    ) {
        $this->pipeline = $pipeline;
        $this->responseProcessor = $responseProcessor;
        $this->storeId = $storeId;
    }

    /**
     * Convert shipment requests to shipment orders, inform label status management, send to API, return result.
     *
     * The mapped result can be
     * - an array of tracking-label pairs or
     * - an array of errors.
     *
     * Note that the SDK does not return errors per shipment, only accumulated into one exception message.
     *
     * @param Request[] $shipmentRequests
     * @return LabelResponseInterface[]|ShipmentErrorResponseInterface[]
     */
    public function createShipments(array $shipmentRequests): array
    {
        /** @var ArtifactsContainer $artifactsContainer */
        $artifactsContainer = $this->pipeline->run($this->storeId, $shipmentRequests);

        $this->responseProcessor->processResponse(
            $artifactsContainer->getLabelResponses(),
            $artifactsContainer->getErrorResponses()
        );

        return array_merge($artifactsContainer->getErrorResponses(), $artifactsContainer->getLabelResponses());
    }
}
