<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Rate;

use Dhl\EcomUs\Model\Config\ModuleConfig;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Netresearch\ShippingCore\Api\Rate\RateRequestEmulationInterface;

/**
 * Abstraction layer for providing the carrier with rates
 */
class RatesManagement
{
    /**
     * @var RateRequestEmulationInterface
     */
    private $rateRequestService;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    public function __construct(RateRequestEmulationInterface $rateRequestService, ModuleConfig $moduleConfig)
    {
        $this->rateRequestService = $rateRequestService;
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * Fetch rates from emulated carrier.
     *
     * @param RateRequest $rateRequest
     * @return bool|Result
     */
    public function collectRates(RateRequest $rateRequest)
    {
        $storeId = $rateRequest->getStoreId();
        $carrierCode = $this->moduleConfig->getProxyCarrierCode($storeId);

        return $this->rateRequestService->emulateRateRequest($carrierCode, $rateRequest);
    }
}
