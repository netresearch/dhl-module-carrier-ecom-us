<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment;

use Dhl\EcomUs\Model\Config\Source\TermsOfTrade;
use Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest\Data\PackageAdditional;
use Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest\RequestExtractorFactory;
use Dhl\Sdk\EcomUs\Api\LabelRequestBuilderInterface;
use Dhl\Sdk\EcomUs\Exception\RequestValidatorException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Shipping\Model\Shipment\Request;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageInterface;
use Netresearch\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageItemInterface;
use Netresearch\ShippingCore\Api\Util\UnitConverterInterface;

/**
 * Request mapper.
 */
class RequestDataMapper
{
    /**
     * @var RequestExtractorFactory
     */
    private $requestExtractorFactory;

    /**
     * @var LabelRequestBuilderInterface
     */
    private $requestBuilder;

    /**
     * @var UnitConverterInterface
     */
    private $unitConverter;

    public function __construct(
        LabelRequestBuilderInterface $requestBuilder,
        RequestExtractorFactory $requestExtractorFactory,
        UnitConverterInterface $unitConverter
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->requestExtractorFactory = $requestExtractorFactory;
        $this->unitConverter = $unitConverter;
    }

    /**
     * Map the Magento shipment request to an SDK request object using the SDK request builder.
     *
     * @param Request $request The shipment request
     *
     * @return \JsonSerializable
     * @throws LocalizedException
     */
    public function mapRequest(Request $request): \JsonSerializable
    {
        $requestExtractor = $this->requestExtractorFactory->create(['shipmentRequest' => $request]);

        $this->requestBuilder->setShipperAccount(
            $requestExtractor->getPickupAccountNumber(),
            $requestExtractor->getDistributionCenter()
        );

        /** @var PackageInterface $package */
        foreach ($requestExtractor->getPackages() as $packageId => $package) {
            /** @var PackageAdditional $packageAdditional */
            $packageAdditional = $package->getPackageAdditional();

            $weightUom = $this->unitConverter->normalizeWeightUnit($package->getWeightUom());
            $this->requestBuilder->setPackageDetails(
                $package->getProductCode(),
                $requestExtractor->getBaseCurrencyCode(),
                $package->getWeight(),
                strtoupper($weightUom)
            );

            if ($package->getLength() || $package->getWidth() || $package->getHeight()) {
                $dimensionsUom = $this->unitConverter->normalizeDimensionUnit($package->getDimensionsUom());
                $this->requestBuilder->setPackageDimensions(
                    $package->getLength(),
                    $package->getWidth(),
                    $package->getHeight(),
                    strtoupper($dimensionsUom)
                );
            }

            $this->requestBuilder->setPackageId($requestExtractor->getUniquePackageId((string) $packageId));
            $this->requestBuilder->setBillingReference($packageAdditional->getBillingReference());
            $this->requestBuilder->setPackageDescription($packageAdditional->getDescription());
            $this->requestBuilder->setRecipientAddress(
                $requestExtractor->getRecipient()->getCountryCode(),
                $requestExtractor->getRecipient()->getPostalCode(),
                $requestExtractor->getRecipient()->getCity(),
                $requestExtractor->getRecipient()->getStreet(),
                $requestExtractor->getRecipient()->getContactPersonName(),
                $requestExtractor->getRecipient()->getContactCompanyName(),
                $requestExtractor->getRecipient()->getContactEmail(),
                $requestExtractor->getRecipient()->getContactPhoneNumber(),
                $requestExtractor->getRecipient()->getState()
            );
            $this->requestBuilder->setReturnAddress(
                $requestExtractor->getShipper()->getCountryCode(),
                $requestExtractor->getShipper()->getPostalCode(),
                $requestExtractor->getShipper()->getCity(),
                $requestExtractor->getShipper()->getStreet(),
                $requestExtractor->getShipper()->getContactCompanyName(),
                $requestExtractor->getShipper()->getContactPersonName(),
                $requestExtractor->getShipper()->getContactEmail(),
                $requestExtractor->getShipper()->getContactPhoneNumber(),
                $requestExtractor->getShipper()->getState()
            );

            if ($package->getCustomsValue() !== null) {
                // customs value indicates cross-border shipment
                $this->requestBuilder->setDeclaredValue($package->getCustomsValue());
                $this->requestBuilder->setDutiesPaid($packageAdditional->getTermsOfTrade() === TermsOfTrade::DDP);
                $this->requestBuilder->setDangerousGoodsCategory($packageAdditional->getDgCategory());

                /** @var PackageItemInterface $packageItem */
                foreach ($requestExtractor->getPackageItems() as $packageItem) {
                    $this->requestBuilder->addExportItem(
                        $packageItem->getExportDescription(),
                        $packageItem->getCountryOfOrigin(),
                        $packageItem->getCustomsValue(),
                        $packageItem->getHsCode(),
                        (int) $packageItem->getQty(),
                        $packageItem->getSku()
                    );
                }
            }
        }

        try {
            return $this->requestBuilder->create();
        } catch (RequestValidatorException $exception) {
            $message = __('Web service request could not be created: %1', $exception->getMessage());
            throw new LocalizedException($message);
        }
    }
}
