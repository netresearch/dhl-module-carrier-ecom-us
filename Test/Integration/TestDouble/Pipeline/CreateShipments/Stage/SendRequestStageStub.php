<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\TestDouble\Pipeline\CreateShipments\Stage;

use Dhl\EcomUs\Model\Pipeline\CreateShipments\ArtifactsContainer;
use Dhl\EcomUs\Model\Pipeline\CreateShipments\Stage\SendRequestStage;
use Dhl\Sdk\EcomUs\Api\Data\LabelInterface;
use Dhl\Sdk\EcomUs\Exception\ServiceException;
use Dhl\Sdk\EcomUs\Service\LabelService\Label;
use Dhl\ShippingCore\Api\Data\Pipeline\ArtifactsContainerInterface;
use Magento\Shipping\Model\Shipment\Request;

/**
 * Class SendRequestStageStub
 */
class SendRequestStageStub extends SendRequestStage
{
    /**
     * Magento shipment request objects passed to the stage. Can be used for assertions.
     *
     * @var Request[]
     */
    public $shipmentRequests = [];

    /**
     * API request objects sent to the web service. Can be used for assertions.
     *
     * @var \JsonSerializable[]
     */
    public $apiRequests = [];

    /**
     * Regular API responses. Built during runtime from the given shipment requests.
     *
     * @var LabelInterface[]
     */
    public $apiResponses;

    /**
     * API response callback. Can be used to alter the default response during runtime, e.g. throw an exception.
     *
     * @var callable|null
     */
    public $responseCallback;

    /**
     * Send label request objects to shipment service.
     *
     * @param Request[] $requests
     * @param ArtifactsContainerInterface|ArtifactsContainer $artifactsContainer
     * @return Request[]
     */
    public function execute(array $requests, ArtifactsContainerInterface $artifactsContainer): array
    {
        $this->shipmentRequests = $requests;
        $this->apiRequests = $artifactsContainer->getApiRequests();
        $this->apiResponses = [];

        foreach ($requests as $requestIndex => $shipmentRequest) {
            $response = null;

            if (is_callable($this->responseCallback)) {
                // let the callback determine the web service response
                $response = ($this->responseCallback)($shipmentRequest);
            }

            if (empty($response)) {
                // generate a positive response
                $orderId = $shipmentRequest->getOrderShipment()->getOrderId();
                $packageId = $shipmentRequest->getData('package_id');

                $response = new Label(
                    (string) $requestIndex,
                    "{$orderId}-{$requestIndex}-{$packageId}",
                    "{$orderId}-{$requestIndex}-{$packageId}",
                    'PNG',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAA')
                );
            }

            $this->apiResponses[$requestIndex] = $response;
        }

        return parent::execute($requests, $artifactsContainer);
    }
}
