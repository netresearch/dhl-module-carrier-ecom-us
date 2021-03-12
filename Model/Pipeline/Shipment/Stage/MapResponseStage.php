<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\Stage;

use Dhl\EcomUs\Model\Pipeline\Shipment\ArtifactsContainer;
use Dhl\EcomUs\Model\Pipeline\Shipment\ResponseDataMapper;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Data\Pipeline\ArtifactsContainerInterface;
use Netresearch\ShippingCore\Api\Pipeline\CreateShipmentsStageInterface;

class MapResponseStage implements CreateShipmentsStageInterface
{
    /**
     * @var ResponseDataMapper
     */
    private $responseDataMapper;

    public function __construct(ResponseDataMapper $responseDataMapper)
    {
        $this->responseDataMapper = $responseDataMapper;
    }

    /**
     * Transform collected results into response objects suitable for processing by the core.
     *
     * @param Request[] $requests
     * @param ArtifactsContainerInterface|ArtifactsContainer $artifactsContainer
     * @return Request[]
     */
    public function execute(array $requests, ArtifactsContainerInterface $artifactsContainer): array
    {
        $stageErrors = $artifactsContainer->getErrors();
        $apiResponses = $artifactsContainer->getApiResponses();

        foreach ($stageErrors as $requestIndex => $details) {
            // no response received from webservice for particular shipment request
            $response = $this->responseDataMapper->createErrorResponse(
                (string) $requestIndex,
                __('Label could not be created: %1', $details['message']),
                $details['shipment']
            );
            $artifactsContainer->addErrorResponse((string) $requestIndex, $response);
        }

        foreach ($requests as $requestIndex => $shipmentRequest) {
            if (isset($stageErrors[$requestIndex])) {
                // errors from previous stages were already processed above
                continue;
            }

            $shipment = $shipmentRequest->getOrderShipment();
            $orderIncrementId = $shipment->getOrder()->getIncrementId();

            if (isset($apiResponses[$requestIndex])) {
                // positive response received from webservice
                $response = $this->responseDataMapper->createShipmentResponse(
                    $apiResponses[$requestIndex],
                    $shipmentRequest->getOrderShipment()
                );

                $artifactsContainer->addLabelResponse((string)$requestIndex, $response);
            } else {
                // negative response received from webservice, details available in api log
                $response = $this->responseDataMapper->createErrorResponse(
                    (string)$requestIndex,
                    __('Label for order %1, package %2 could not be created.', $orderIncrementId, $requestIndex),
                    $shipmentRequest->getOrderShipment()
                );

                $artifactsContainer->addErrorResponse((string)$requestIndex, $response);
            }
        }

        return $requests;
    }
}
