<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class ModuleConfig
 *
 * Provide central access to module configuration settings.
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link   https://www.netresearch.de/
 */
class ModuleConfig
{
    // 400_checkout_presentation.xml
    private const CONFIG_PATH_PROXY_CARRIER = 'dhlshippingsolutions/dhlecomus/checkout_settings/emulated_carrier';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * ModuleConfig constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get the code of the carrier to forward rate requests to.
     *
     * @param mixed $store
     * @return string
     */
    public function getProxyCarrierCode($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_PROXY_CARRIER,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
