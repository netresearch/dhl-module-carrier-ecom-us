<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\Stage;

use Dhl\EcomUs\Model\Pipeline\Shipment\ArtifactsContainer;
use Magento\Framework\Exception\ValidatorException;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Data\Pipeline\ArtifactsContainerInterface;
use Netresearch\ShippingCore\Api\Pipeline\CreateShipmentsStageInterface;
use Netresearch\ShippingCore\Api\Pipeline\ShipmentRequest\RequestValidatorInterface;

class ValidateStage implements CreateShipmentsStageInterface
{
    /**
     * @var RequestValidatorInterface
     */
    private $requestValidator;

    public function __construct(RequestValidatorInterface $requestValidator)
    {
        $this->requestValidator = $requestValidator;
    }

    /**
     * Validate shipment requests.
     *
     * Invalid requests are removed from shipment requests and instantly added as label failures.
     *
     * @param Request[] $requests
     * @param ArtifactsContainerInterface|ArtifactsContainer $artifactsContainer
     * @return Request[]
     */
    public function execute(array $requests, ArtifactsContainerInterface $artifactsContainer): array
    {
        $callback = function (Request $request, int $requestIndex) use ($artifactsContainer) {
            try {
                $this->requestValidator->validate($request);
                return true;
            } catch (ValidatorException $exception) {
                $artifactsContainer->addError(
                    (string) $requestIndex,
                    $request->getOrderShipment(),
                    $exception->getMessage()
                );
                return false;
            }
        };

        // pass on only the shipment requests that validate
        return array_filter($requests, $callback, ARRAY_FILTER_USE_BOTH);
    }
}
