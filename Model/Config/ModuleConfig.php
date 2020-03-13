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
    // Defaults
    const CONFIG_PATH_VERSION = 'carriers/dhlecomus/version';

    // 100_general_settings.xml
    public const CONFIG_PATH_ENABLE_LOGGING = 'dhlshippingsolutions/dhlecomus/general_shipping_settings/logging';
    public const CONFIG_PATH_LOGLEVEL = 'dhlshippingsolutions/dhlecomus/general_shipping_settings/logging_group/loglevel';

    // 200_account_settings.xml
    public const CONFIG_PATH_PICKUP_ACCOUNT = 'dhlshippingsolutions/dhlecomus/account_settings/pickup_account_number';
    public const CONFIG_PATH_DISTRIBUTION_CENTER = 'dhlshippingsolutions/dhlecomus/account_settings/distribution_center';
    private const CONFIG_PATH_SANDBOX_MODE = 'dhlshippingsolutions/dhlecomus/account_settings/sandboxmode';
    private const CONFIG_PATH_USERNAME = 'dhlshippingsolutions/dhlecomus/account_settings/api_username';
    private const CONFIG_PATH_PASSWORD = 'dhlshippingsolutions/dhlecomus/account_settings/api_password';

    // 400_checkout_presentation.xml
    private const CONFIG_PATH_PROXY_CARRIER = 'dhlshippingsolutions/dhlecomus/checkout_settings/emulated_carrier';

    // 500_shipment_defaults.xml
    private const CONFIG_PATH_DEFAULT_PRODUCTS = 'dhlshippingsolutions/dhlecomus/shipment_defaults/shipping_products';

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
     * Obtain the module version.
     *
     * @return string
     */
    public function getModuleVersion(): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_VERSION);
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

    /**
     * Check if sandbox mode is enabled.
     *
     * @param mixed $store
     * @return bool
     */
    public function isSandboxMode($store = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::CONFIG_PATH_SANDBOX_MODE,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain Pickup Account Number.
     *
     * @param mixed $store
     * @return string
     */
    public function getPickupAccountNumber($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_PICKUP_ACCOUNT,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Obtain distribution center.
     *
     * @param mixed $store
     * @return string
     */
    public function getDistributionCenter($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_DISTRIBUTION_CENTER,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the user's name (API user credentials).
     *
     * @param mixed $store
     * @return string
     */
    public function getApiUser($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_USERNAME,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get the user's password (API user credentials).
     *
     * @param mixed $store
     * @return string
     */
    public function getApiPassword($store = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::CONFIG_PATH_PASSWORD,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Get default product per destination, e.g.
     *
     * - ["CA" => ["CA => "GND", "INTL" => "PLT"]]
     *
     * @param mixed $store
     * @return string[]
     */
    public function getDefaultProducts($store = null): array
    {
        $products = $this->scopeConfig->getValue(
            self::CONFIG_PATH_DEFAULT_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        $defaultProducts = [];
        $products = array_column($products, 'product', 'route');
        foreach ($products as $route => $product) {
            $locations = explode('-', $route);
            $defaultProducts[$locations[0]][$locations[1]] = $product;
        }

        return $defaultProducts;
    }
}
