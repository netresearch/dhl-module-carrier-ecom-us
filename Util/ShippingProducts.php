<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Util;

use Dhl\EcomUs\Model\Config\ModuleConfig;

/**
 * ShippingProducts
 *
 * Utility to access
 * - DHL eCommerce US shipping product codes
 * - DHL eCommerce US shipping product names
 * - DHL eCommerce US shipping product routes
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link   https://www.netresearch.de/
 */
class ShippingProducts
{
    /**
     * Destination regions.
     */
    private const REGION_INTERNATIONAL = 'INTL';

    /**
     * Destination country codes.
     */
    private const COUNTRY_CODE_US = 'US';
    private const COUNTRY_CODE_CANADA = 'CA';

    /**
     * Product codes.
     */
    private const CODE_DOM_PARCEL_EXPEDITED = 'EXP';
    private const CODE_DOM_PARCEL_EXPEDITED_MAX = 'MAX';
    private const CODE_DOM_PARCEL_GROUND = 'GND';
    private const CODE_DOM_BPM_EXPEDITED = 'BEX';
    private const CODE_DOM_BPM_GROUND = 'BGN';

    private const CODE_INTL_PARCEL_EXPEDITED_MAX = 'PLT';
    private const CODE_INTL_PARCEL_MAX = 'PLY';
    private const CODE_INTL_PARCEL_GROUND = 'PKY';

    /**
     * @var ModuleConfig
     */
    private $config;

    /**
     * ShippingProducts constructor.
     * @param ModuleConfig $config
     */
    public function __construct(ModuleConfig $config)
    {
        $this->config = $config;
    }

    /**
     * Obtain all origin-destination-products combinations.
     *
     * @return string[][][]
     */
    private function getProducts(): array
    {
        return [
            self::COUNTRY_CODE_US => [
                self::COUNTRY_CODE_US => [
                    self::CODE_DOM_PARCEL_EXPEDITED,
                    self::CODE_DOM_PARCEL_EXPEDITED_MAX,
                    self::CODE_DOM_PARCEL_GROUND,
                    self::CODE_DOM_BPM_EXPEDITED,
                    self::CODE_DOM_BPM_GROUND,
                ],
                self::REGION_INTERNATIONAL => [
                    self::CODE_INTL_PARCEL_EXPEDITED_MAX,
                    self::CODE_INTL_PARCEL_MAX,
                    self::CODE_INTL_PARCEL_GROUND,
                ],
            ],
            self::COUNTRY_CODE_CANADA => [
                self::COUNTRY_CODE_CANADA => [],
                self::REGION_INTERNATIONAL => [
                    self::CODE_INTL_PARCEL_EXPEDITED_MAX,
                    self::CODE_INTL_PARCEL_MAX,
                    self::CODE_INTL_PARCEL_GROUND,
                ],
            ],
        ];
    }

    /**
     * Obtain human readable name for given product code.
     *
     * @param string $productCode
     *
     * @return string
     */
    public function getProductName(string $productCode): string
    {
        $names = [
            self::CODE_DOM_PARCEL_EXPEDITED => 'DHL Parcel Expedited',
            self::CODE_DOM_PARCEL_EXPEDITED_MAX => 'DHL Parcel Expedited Max',
            self::CODE_DOM_PARCEL_GROUND => 'DHL Parcel Ground',
            self::CODE_DOM_BPM_EXPEDITED => 'DHL BPM Expedited',
            self::CODE_DOM_BPM_GROUND => 'DHL BPM Ground',
            self::CODE_INTL_PARCEL_EXPEDITED_MAX => 'DHL Parcel Expedited Max',
            self::CODE_INTL_PARCEL_MAX => 'DHL Parcel Max',
            self::CODE_INTL_PARCEL_GROUND => 'DHL Parcel Ground',
        ];

        if (!isset($names[$productCode])) {
            return $productCode;
        }

        return $names[$productCode];
    }

    /**
     * For every available destination region, obtain the default product.
     *
     * If no default products are configured, the first available product is returned.
     *
     * @param string $originCountryCode
     * @param mixed $store
     * @return string[]
     */
    public function getDefaultProducts(string $originCountryCode, $store = null): array
    {
        $products = $this->config->getDefaultProducts($store);
        if (array_key_exists($originCountryCode, $products)) {
            return $products[$originCountryCode];
        }

        $products = $this->getProducts();
        if (array_key_exists($originCountryCode, $products)) {
            return array_map('array_shift', $products[$originCountryCode]);
        }

        return [];
    }

    /**
     * Get shipping product codes for given shipping origin.
     *
     * Returns an array of [$destination => $codes]. Destinations may be identified by country code, "EU" or "INTL".
     *
     * @param string $originCountryCode
     * @return string[][]
     */
    public function getApplicableProducts(string $originCountryCode): array
    {
        $products = $this->getProducts();
        if (array_key_exists($originCountryCode, $products)) {
            return $products[$originCountryCode];
        }

        return [];
    }

    /**
     * Get shipping product codes for given shipping origin and destination.
     *
     * Returns an array of [$destination => $codes]. Destinations may be identified by country code or "INTL".
     *
     * @param string $originCountryCode
     * @param string $destinationCountryCode
     * @return string[]
     */
    public function getShippingProducts(string $originCountryCode, string $destinationCountryCode): array
    {
        // load product codes applicable to the given origin
        $applicableProducts = $this->getApplicableProducts($originCountryCode);

        // reduce to product codes applicable to the given destination
        if (isset($applicableProducts[$destinationCountryCode])) {
            $destinationRegion = $destinationCountryCode;
        } else {
            $destinationRegion = self::REGION_INTERNATIONAL;
        }

        return [$destinationRegion => $applicableProducts[$destinationRegion] ?? []];
    }
}
