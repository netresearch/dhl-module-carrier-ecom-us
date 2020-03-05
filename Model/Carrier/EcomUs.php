<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Carrier;

use Dhl\EcomUs\Model\Config\ModuleConfig;
use Dhl\EcomUs\Model\Rate\RatesManagement;
use Dhl\ShippingCore\Model\Rate\Emulation\ProxyCarrierFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Directory\Helper\Data;
use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrierInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory;
use Magento\Shipping\Model\Simplexml\ElementFactory;
use Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackErrorFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Magento\Shipping\Model\Tracking\ResultFactory as TrackResultFactory;
use Psr\Log\LoggerInterface;

/**
 * DHL eCommerce Americas online shipping carrier model.
 */
class EcomUs extends AbstractCarrierOnline implements CarrierInterface
{
    public const CARRIER_CODE = 'dhlecomus';

    /**
     * @var string
     */
    protected $_code = self::CARRIER_CODE;

    /**
     * @var RatesManagement
     */
    private $ratesManagement;

    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var ProxyCarrierFactory
     */
    private $proxyCarrierFactory;

    /**
     * @var AbstractCarrierInterface
     */
    private $proxyCarrier;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Security $xmlSecurity,
        ElementFactory $xmlElFactory,
        RateResultFactory $rateFactory,
        MethodFactory $rateMethodFactory,
        TrackResultFactory $trackFactory,
        TrackErrorFactory $trackErrorFactory,
        StatusFactory $trackStatusFactory,
        RegionFactory $regionFactory,
        CountryFactory $countryFactory,
        CurrencyFactory $currencyFactory,
        Data $directoryData,
        StockRegistryInterface $stockRegistry,
        RatesManagement $ratesManagement,
        ModuleConfig $moduleConfig,
        DataObjectFactory $dataObjectFactory,
        ProxyCarrierFactory $proxyCarrierFactory,
        array $data = []
    ) {
        $this->ratesManagement = $ratesManagement;
        $this->moduleConfig = $moduleConfig;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->proxyCarrierFactory = $proxyCarrierFactory;

        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    /**
     * Returns the configured proxied carrier instance.
     *
     * @return AbstractCarrierInterface
     * @throws NotFoundException
     */
    private function getProxyCarrier()
    {
        if (!$this->proxyCarrier) {
            $storeId = $this->getData('store');
            $carrierCode = $this->moduleConfig->getProxyCarrierCode($storeId);

            $this->proxyCarrier = $this->proxyCarrierFactory->create($carrierCode);
        }

        return $this->proxyCarrier;
    }

    public function collectRates(RateRequest $request)
    {
        $result = $this->_rateFactory->create();

        if ($this->_activeFlag && !$this->getConfigFlag($this->_activeFlag)) {
            return $result;
        }
        // set carrier details for rate post-processing
        $request->setData('carrier_code', $this->getCarrierCode());
        $request->setData('carrier_title', $this->getConfigData('title'));

        $proxyResult = $this->ratesManagement->collectRates($request);
        if (!$proxyResult) {
            $result->append($this->getErrorMessage());

            return $result;
        }

        return $proxyResult;
    }

    /**
     * Perform a shipment request to the DHL eCommerce Americas web service.
     *
     * Return either tracking number and label data or a shipment error.
     * Note that Magento triggers one web service request per package in multi-package shipments.
     *
     * @param DataObject $request
     * @return DataObject
     * @see \Magento\Shipping\Model\Carrier\AbstractCarrierOnline::requestToShipment
     * @see \Magento\Shipping\Model\Carrier\AbstractCarrierOnline::returnOfShipment
     */
    protected function _doShipmentRequest(DataObject $request)
    {
        return $this->dataObjectFactory->create(['data' => ['errors' => 'Not yet implemented.']]);
    }

    /**
     * Obtain shipping methods offered by the carrier.
     *
     * The DHL eCommerce Americas carrier does not offer own methods. The call gets
     * forwarded to another carrier as configured via module settings.
     *
     * @return string[] Associative array of method names with method code as key.
     */
    public function getAllowedMethods(): array
    {
        try {
            $carrier = $this->getProxyCarrier();
        } catch (LocalizedException $exception) {
            return [];
        }

        if (!$carrier instanceof CarrierInterface) {
            return [];
        }

        return $carrier->getAllowedMethods();
    }
}
