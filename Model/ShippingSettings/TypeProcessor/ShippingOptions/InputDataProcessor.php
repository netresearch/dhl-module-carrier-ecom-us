<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\ShippingSettings\TypeProcessor\ShippingOptions;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Dhl\EcomUs\Model\Config\Source\TermsOfTrade;
use Dhl\EcomUs\Model\ShippingSettings\ShippingOption\Codes;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\OptionInterfaceFactory;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\TypeProcessor\ShippingOptionsProcessorInterface;
use Netresearch\ShippingCore\Model\ItemAttribute\ShipmentItemAttributeReader;

class InputDataProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * @var ShipmentItemAttributeReader
     */
    private $itemAttributeReader;

    /**
     * @var TermsOfTrade
     */
    private $termsOfTrade;

    /**
     * @var OptionInterfaceFactory
     */
    private $optionFactory;

    public function __construct(
        ShipmentItemAttributeReader $itemAttributeReader,
        TermsOfTrade $termsOfTrade,
        OptionInterfaceFactory $optionFactory
    ) {
        $this->itemAttributeReader = $itemAttributeReader;
        $this->termsOfTrade = $termsOfTrade;
        $this->optionFactory = $optionFactory;
    }

    /**
     * Set options and values to inputs on package level.
     *
     * @param ShippingOptionInterface $shippingOption
     * @param ShipmentInterface $shipment
     */
    private function processInputs(ShippingOptionInterface $shippingOption, ShipmentInterface $shipment)
    {
        foreach ($shippingOption->getInputs() as $input) {
            switch ($input->getCode()) {
                case Codes::PACKAGE_INPUT_TERMS_OF_TRADE:
                    $fnCreateOptions = function (array $optionArray) {
                        $option = $this->optionFactory->create();
                        $option->setValue((string) $optionArray['value']);
                        $option->setLabel((string) $optionArray['label']);
                        return $option;
                    };

                    $input->setOptions(array_map($fnCreateOptions, $this->termsOfTrade->toOptionArray()));
                    break;
                case Codes::PACKAGE_INPUT_DESCRIPTION:
                    $exportDescriptions = $this->itemAttributeReader->getPackageExportDescriptions($shipment);
                    $exportDescription = implode(', ', $exportDescriptions);
                    $input->setDefaultValue(substr($exportDescription, 0, 80));
                    break;
            }
        }
    }

    /**
     * @param string $carrierCode
     * @param ShippingOptionInterface[] $shippingOptions
     * @param int $storeId
     * @param string $countryCode
     * @param string $postalCode
     * @param ShipmentInterface|null $shipment
     *
     * @return ShippingOptionInterface[]
     */
    public function process(
        string $carrierCode,
        array $shippingOptions,
        int $storeId,
        string $countryCode,
        string $postalCode,
        ShipmentInterface $shipment = null
    ): array {
        if ($carrierCode !== EcomUs::CARRIER_CODE) {
            // different carrier, nothing to modify.
            return $shippingOptions;
        }

        if (!$shipment) {
            return $shippingOptions;
        }

        foreach ($shippingOptions as $shippingOption) {
            $this->processInputs($shippingOption, $shipment);
        }

        return $shippingOptions;
    }
}
