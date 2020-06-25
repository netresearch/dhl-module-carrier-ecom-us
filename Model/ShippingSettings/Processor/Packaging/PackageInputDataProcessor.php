<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\ShippingSettings\Processor\Packaging;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Dhl\EcomUs\Util\ShippingProducts;
use Dhl\ShippingCore\Api\ConfigInterface;
use Dhl\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Dhl\ShippingCore\Api\ShippingSettings\Processor\Packaging\ShippingOptionsProcessorInterface;
use Dhl\ShippingCore\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Model\Order;

/**
 * Class PackageInputDataProcessor
 *
 * Prepare package option values.
 *
 * @author Sebastian Ertner <sebastian.ertner@netresearch.de>
 */
class PackageInputDataProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * @var ConfigInterface
     */
    private $dhlConfig;

    /**
     * @var ShippingProducts
     */
    private $shippingProducts;

    /**
     * PackageInputDataProcessor constructor.
     *
     * @param ConfigInterface $dhlConfig
     * @param ShippingProducts $shippingProducts
     */
    public function __construct(
        ConfigInterface $dhlConfig,
        ShippingProducts $shippingProducts
    ) {
        $this->dhlConfig = $dhlConfig;
        $this->shippingProducts = $shippingProducts;
    }

    /**
     * Set options and values to inputs on package level.
     *
     * @param ShippingOptionInterface $shippingOption
     * @param ShipmentInterface $shipment
     * @return void
     */
    private function processInputs(ShippingOptionInterface $shippingOption, ShipmentInterface $shipment): void
    {
        foreach ($shippingOption->getInputs() as $input) {
            switch ($input->getCode()) {
                case Codes::PACKAGING_INPUT_PRODUCT_CODE:
                    $storeId = $shipment->getStoreId();

                    /** @var Order $order */
                    $order = $shipment->getOrder();
                    $originCountry = $this->dhlConfig->getOriginCountry($storeId);
                    $destinationCountry = $order->getShippingAddress()->getCountryId();

                    $applicableProducts = $this->shippingProducts->getShippingProducts(
                        $originCountry,
                        $destinationCountry
                    );

                    $options = [];
                    foreach ($applicableProducts as $regionId => $regionProducts) {
                        foreach ($regionProducts as $productCode) {
                            $options[] = [
                                'value' => $productCode,
                                'label' => $this->shippingProducts->getProductName($productCode),
                            ];
                        }
                    }
                    $input->setOptions($options);

                    $inputDefault = '';
                    $defaultProducts = $this->shippingProducts->getDefaultProducts($originCountry, $storeId);
                    foreach ($defaultProducts as $regionId => $regionDefault) {
                        if (!isset($applicableProducts[$regionId])) {
                            continue;
                        }

                        if (in_array($regionDefault, $applicableProducts[$regionId], true)) {
                            $inputDefault = $regionDefault;
                            break;
                        }
                    }

                    if (!$inputDefault) {
                        // no defaults configured, use first available applicable product
                        $inputDefault = current(current($applicableProducts));
                    }
                    $input->setDefaultValue((string)$inputDefault);
                    break;
            }
        }
    }

    /**
     * @param ShippingOptionInterface[] $optionsData
     * @param ShipmentInterface $shipment
     *
     * @return ShippingOptionInterface[]
     */
    public function process(array $optionsData, ShipmentInterface $shipment): array
    {
        /** @var Order $order */
        $order = $shipment->getOrder();
        $carrierCode = strtok((string) $order->getShippingMethod(), '_');

        if ($carrierCode !== EcomUs::CARRIER_CODE) {
            return $optionsData;
        }

        foreach ($optionsData as $optionGroup) {
            $this->processInputs($optionGroup, $shipment);
        }

        return $optionsData;
    }
}
