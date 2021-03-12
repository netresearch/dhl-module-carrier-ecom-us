<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\ShippingSettings\TypeProcessor\ShippingOptions;

use Dhl\EcomUs\Model\Carrier\EcomUs;
use Magento\Sales\Api\Data\ShipmentInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOption\InputInterface;
use Netresearch\ShippingCore\Api\Data\ShippingSettings\ShippingOptionInterface;
use Netresearch\ShippingCore\Api\ShippingSettings\TypeProcessor\ShippingOptionsProcessorInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes;

class RemoveExportDescriptionProcessor implements ShippingOptionsProcessorInterface
{
    /**
     * Remove the export description input. DHL eCom US uses package description for both, xbo and dom shipments.
     *
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
            // checkout scope, nothing to modify.
            return $shippingOptions;
        }

        $packageCustoms = $shippingOptions[Codes::PACKAGE_OPTION_CUSTOMS] ?? false;
        if (!$packageCustoms instanceof ShippingOptionInterface) {
            // not the package customs option, proceed.
            return $shippingOptions;
        }

        $inputs = array_filter(
            $packageCustoms->getInputs(),
            function (InputInterface $input) {
                return $input->getCode() !== Codes::PACKAGE_INPUT_EXPORT_DESCRIPTION;
            }
        );
        $packageCustoms->setInputs($inputs);

        return $shippingOptions;
    }
}
