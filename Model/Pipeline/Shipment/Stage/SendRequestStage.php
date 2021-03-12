<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\Stage;

use Dhl\EcomUs\Model\Pipeline\Shipment\ArtifactsContainer;
use Dhl\EcomUs\Model\Webservice\LabelServiceFactory;
use Dhl\Sdk\EcomUs\Exception\DetailedServiceException;
use Dhl\Sdk\EcomUs\Exception\ServiceException;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Data\Pipeline\ArtifactsContainerInterface;
use Netresearch\ShippingCore\Api\Pipeline\CreateShipmentsStageInterface;

class SendRequestStage implements CreateShipmentsStageInterface
{
    /**
     * @var LabelServiceFactory
     */
    private $labelServiceFactory;

    public function __construct(LabelServiceFactory $labelServiceFactory)
    {
        $this->labelServiceFactory = $labelServiceFactory;
    }

    /**
     * Send label request objects to shipment service.
     *
     * @param Request[] $requests
     * @param ArtifactsContainerInterface|ArtifactsContainer $artifactsContainer
     * @return Request[]
     */
    public function execute(array $requests, ArtifactsContainerInterface $artifactsContainer): array
    {
        $apiRequests = $artifactsContainer->getApiRequests();
        if (empty($apiRequests)) {
            return [];
        }

        $labelService = $this->labelServiceFactory->create(['storeId' => $artifactsContainer->getStoreId()]);

        $callback = function (Request $request, int $requestIndex) use ($labelService, $artifactsContainer) {
            try {
                $label = $labelService->createLabel($artifactsContainer->getApiRequests()[$requestIndex]);
                $artifactsContainer->addApiResponse((string)$requestIndex, $label);

                return true;
            } catch (DetailedServiceException $exception) {
                $artifactsContainer->addError(
                    (string)$requestIndex,
                    $request->getOrderShipment(),
                    $exception->getMessage()
                );

                return false;
            } catch (ServiceException $exception) {
                $artifactsContainer->addError(
                    (string)$requestIndex,
                    $request->getOrderShipment(),
                    'Web service request failed.'
                );

                return false;
            }
        };

        // pass on only successfully processed shipment requests to the next stage
        return array_filter($requests, $callback, ARRAY_FILTER_USE_BOTH);
    }
}
