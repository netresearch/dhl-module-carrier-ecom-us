<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest;

use Dhl\EcomUs\Model\Config\ModuleConfig;
use Dhl\EcomUs\Model\Pipeline\Shipment\ShipmentRequest\Data\PackageAdditionalFactory;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentRequest\PackageInterfaceFactory;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentRequest\RecipientInterface;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentRequest\ShipperInterface;
use Dhl\ShippingCore\Api\Pipeline\ShipmentRequest\RequestExtractorInterface;
use Dhl\ShippingCore\Api\Pipeline\ShipmentRequest\RequestExtractorInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Shipping\Model\Shipment\Request;
use Zend\Hydrator\Reflection;

/**
 * Class RequestExtractor
 *
 * The original shipment request is a rather limited DTO with unstructured data (DataObject, array).
 * The extractor and its subtypes offer a well-defined interface to extract the request data and
 * isolates the toxic part of extracting unstructured array data from the shipment request.
 */
class RequestExtractor implements RequestExtractorInterface
{
    /**
     * @var RequestExtractorInterfaceFactory
     */
    private $requestExtractorFactory;

    /**
     * @var PackageAdditionalFactory
     */
    private $packageAdditionalFactory;

    /**
     * @var PackageInterfaceFactory
     */
    private $packageFactory;

    /**
     * @var Request
     */
    private $shipmentRequest;

    /**
     * @var ModuleConfig
     */
    private $config;

    /**
     * @var Reflection
     */
    private $hydrator;

    /**
     * @var RequestExtractorInterface
     */
    private $coreExtractor;

    /**
     * RequestExtractor constructor.
     *
     * @param RequestExtractorInterfaceFactory $requestExtractorFactory
     * @param PackageAdditionalFactory $packageAdditionalFactory
     * @param PackageInterfaceFactory $packageFactory
     * @param Request $shipmentRequest
     * @param ModuleConfig $config
     * @param Reflection $hydrator
     */
    public function __construct(
        RequestExtractorInterfaceFactory $requestExtractorFactory,
        PackageAdditionalFactory $packageAdditionalFactory,
        PackageInterfaceFactory $packageFactory,
        Request $shipmentRequest,
        ModuleConfig $config,
        Reflection $hydrator
    ) {
        $this->requestExtractorFactory = $requestExtractorFactory;
        $this->packageAdditionalFactory = $packageAdditionalFactory;
        $this->packageFactory = $packageFactory;
        $this->shipmentRequest = $shipmentRequest;
        $this->config = $config;
        $this->hydrator = $hydrator;
    }

    /**
     * Obtain core extractor for forwarding generic shipment data calls.
     *
     * @return RequestExtractorInterface
     */
    private function getCoreExtractor(): RequestExtractorInterface
    {
        if (empty($this->coreExtractor)) {
            $this->coreExtractor = $this->requestExtractorFactory->create(
                ['shipmentRequest' => $this->shipmentRequest]
            );
        }

        return $this->coreExtractor;
    }

    public function isReturnShipmentRequest(): bool
    {
        return $this->getCoreExtractor()->isReturnShipmentRequest();
    }

    public function getStoreId(): int
    {
        return $this->getCoreExtractor()->getStoreId();
    }

    public function getBaseCurrencyCode(): string
    {
        return $this->getCoreExtractor()->getBaseCurrencyCode();
    }

    public function getOrder(): Order
    {
        return $this->getCoreExtractor()->getOrder();
    }

    public function getShipment(): Shipment
    {
        return $this->getCoreExtractor()->getShipment();
    }

    public function getShipper(): ShipperInterface
    {
        return $this->getCoreExtractor()->getShipper();
    }

    public function getRecipient(): RecipientInterface
    {
        return $this->getCoreExtractor()->getRecipient();
    }

    public function getPackageWeight(): float
    {
        return $this->getCoreExtractor()->getPackageWeight();
    }

    public function getPackages(): array
    {
        $packages = $this->getCoreExtractor()->getPackages();
        if (count($packages) > 1) {
            throw new LocalizedException(__('Multi package shipments are not supported.'));
        }

        $ecomPackages = [];
        foreach ($packages as $packageId => $package) {
            // read generic export data from shipment request
            $packageParams = $this->shipmentRequest->getData('packages')[$packageId]['params'];
            $customsParams = $packageParams['customs'] ?? [];

            // add eCommerce-specific export data to package data
            $additionalData['dgCategory'] = $customsParams['dgCategory'] ?? '';

            try {
                $packageData = $this->hydrator->extract($package);
                $packageData['packageAdditional'] = $this->packageAdditionalFactory->create($additionalData);

                // create new extended package instance with eCommerce-specific export data
                $ecomPackages[$packageId] = $this->packageFactory->create($packageData);
            } catch (\Exception $exception) {
                throw new LocalizedException(__('An error occurred while preparing package data.'), $exception);
            }
        }

        return $ecomPackages;
    }

    public function getAllItems(): array
    {
        return $this->getCoreExtractor()->getAllItems();
    }

    public function getPackageItems(): array
    {
        return $this->getCoreExtractor()->getPackageItems();
    }

    public function getShipmentDate(): \DateTime
    {
        return $this->getCoreExtractor()->getShipmentDate();
    }

    public function getPickupAccountNumber(): string
    {
        $storeId = $this->getCoreExtractor()->getStoreId();

        //fixme(nr): this should be contained in shipping settings
        return $this->config->getPickupAccountNumber($storeId);
    }

    public function getDistributionCenter(): string
    {
        $storeId = $this->getCoreExtractor()->getStoreId();

        //fixme(nr): this should be contained in shipping settings
        return $this->config->getDistributionCenter($storeId);
    }

    /**
     * Calculate unique package id (Customer Confirmation Number).
     *
     * The "Package ID" is a temporary number for the request. For all
     * further processing, the "DHL Package ID" generated at the web
     * service will be used.
     *
     * @fixme(nr): generate better package id
     *
     * @param string $sequenceNumber
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getUniquePackageId(string $sequenceNumber): string
    {
        $orderId = (string) $this->getOrder()->getId();
        $sequenceNumber = \str_pad($sequenceNumber, 2, '0', STR_PAD_LEFT);

        try {
            $rand = (string) \random_int(0, 99);
        } catch (\Exception $exception) {
            // Fallback to some time based number, which is not exactly random
            $rand = (string) (microtime(true) / 0.000001) % 100;
        }

        return $orderId . $sequenceNumber . $rand;
    }
}
