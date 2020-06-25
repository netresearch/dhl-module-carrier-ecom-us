<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment;

use Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest\Data\PackageAdditional;
use Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest\RequestExtractorFactory;
use Dhl\Sdk\EcomUs\Api\LabelRequestBuilderInterface;
use Dhl\Sdk\EcomUs\Exception\RequestValidatorException;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageInterface;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageItemInterface;
use Dhl\ShippingCore\Api\Util\UnitConverterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Shipping\Model\Shipment\Request;

/**
 * Request mapper.
 *
 * @author Rico Sonntag <rico.sonntag@netresearch.de>
 * @link  https://www.netresearch.de/
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

    /**
     * RequestDataMapper constructor.
     *
     * @param LabelRequestBuilderInterface $requestBuilder
     * @param RequestExtractorFactory $requestExtractorFactory
     * @param UnitConverterInterface $unitConverter
     */
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

            $dimensionsUom = $this->unitConverter->normalizeDimensionUnit($package->getDimensionsUom());
            $this->requestBuilder->setPackageDimensions(
                $package->getLength(),
                $package->getWidth(),
                $package->getHeight(),
                strtoupper($dimensionsUom)
            );

            $this->requestBuilder->setPackageId($requestExtractor->getUniquePackageId((string) $packageId));
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
                $this->requestBuilder->setPackageDescription($package->getExportDescription());
                $this->requestBuilder->setDeclaredValue($package->getCustomsValue());
                $this->requestBuilder->setDutiesPaid($package->getTermsOfTrade() === 'DDP');
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
