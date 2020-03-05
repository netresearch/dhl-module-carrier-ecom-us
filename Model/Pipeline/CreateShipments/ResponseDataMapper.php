<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\CreateShipments;

use Dhl\Sdk\EcomUs\Api\Data\LabelInterface;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentResponse\LabelResponseInterface;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentResponse\LabelResponseInterfaceFactory;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentErrorResponseInterface;
use Dhl\ShippingCore\Api\Data\Pipeline\ShipmentResponse\ShipmentErrorResponseInterfaceFactory;
use Dhl\ShippingCore\Api\Util\PdfCombinatorInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\ShipmentInterface;
use Psr\Log\LoggerInterface;

/**
 * Response mapper.
 *
 * Convert API response into the carrier response format that the shipping module understands.
 *
 * @see \Magento\Shipping\Model\Carrier\AbstractCarrierOnline::requestToShipment
 *
 * @author  Christoph Aßmann <christoph.assmann@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class ResponseDataMapper
{
    /**
     * @var PdfCombinatorInterface
     */
    private $pdfCombinator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LabelResponseInterfaceFactory
     */
    private $shipmentResponseFactory;

    /**
     * @var ShipmentErrorResponseInterfaceFactory
     */
    private $errorResponseFactory;

    /**
     * ResponseDataMapper constructor.
     *
     * @param PdfCombinatorInterface $pdfCombinator
     * @param LabelResponseInterfaceFactory $shipmentResponseFactory
     * @param ShipmentErrorResponseInterfaceFactory $errorResponseFactory
     */
    public function __construct(
        PdfCombinatorInterface $pdfCombinator,
        LabelResponseInterfaceFactory $shipmentResponseFactory,
        ShipmentErrorResponseInterfaceFactory $errorResponseFactory
    ) {
        $this->pdfCombinator = $pdfCombinator;
        $this->shipmentResponseFactory = $shipmentResponseFactory;
        $this->errorResponseFactory = $errorResponseFactory;
    }

    /**
     * Map created shipment into response object as required by the shipping module.
     *
     * @fixme(nr): check if multiple label data may be returned. if so, combine them.
     *
     * @param LabelInterface $label
     * @param ShipmentInterface $salesShipment
     * @return LabelResponseInterface
     */
    public function createShipmentResponse(
        LabelInterface $label,
        ShipmentInterface $salesShipment
    ): LabelResponseInterface {
        $responseData = [
            LabelResponseInterface::REQUEST_INDEX => $label->getPackageId(),
            LabelResponseInterface::SALES_SHIPMENT => $salesShipment,
            LabelResponseInterface::TRACKING_NUMBER => $label->getTrackingNumber(),
            LabelResponseInterface::SHIPPING_LABEL_CONTENT => $label->getLabelData(),
        ];

        return $this->shipmentResponseFactory->create(['data' => $responseData]);
    }

    /**
     * Map error message into response object as required by the shipping module.
     *
     * @param string $requestIndex
     * @param Phrase $message
     * @param ShipmentInterface $salesShipment
     * @return ShipmentErrorResponseInterface
     */
    public function createErrorResponse(
        string $requestIndex,
        Phrase $message,
        ShipmentInterface $salesShipment
    ): ShipmentErrorResponseInterface {
        $responseData = [
            ShipmentErrorResponseInterface::REQUEST_INDEX => $requestIndex,
            ShipmentErrorResponseInterface::ERRORS => $message,
            ShipmentErrorResponseInterface::SALES_SHIPMENT => $salesShipment,
        ];

        return $this->errorResponseFactory->create(['data' => $responseData]);
    }
}
