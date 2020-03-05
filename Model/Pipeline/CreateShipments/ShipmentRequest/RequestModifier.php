<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Model\Pipeline\CreateShipments\ShipmentRequest;

use Dhl\EcomUs\Util\ShippingProducts;
use Dhl\ShippingCore\Api\Pipeline\ShipmentRequest\RequestModifierInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Shipping\Model\Shipment\Request;

/**
 * Class RequestModifier
 *
 * Add defaults to shipment request in bulk actions with no user input.
 */
class RequestModifier implements RequestModifierInterface
{
    /**
     * @var RequestModifierInterface
     */
    private $coreModifier;

    /**
     * @var ShippingProducts
     */
    private $shippingProducts;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * RequestModifier constructor.
     *
     * @param RequestModifierInterface $coreModifier
     * @param ShippingProducts $shippingProducts
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        RequestModifierInterface $coreModifier,
        ShippingProducts $shippingProducts,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->coreModifier = $coreModifier;
        $this->shippingProducts = $shippingProducts;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * Add default shipping product to package params, e.g. EXP or PLT
     *
     * @param Request $shipmentRequest
     * @throws LocalizedException
     */
    private function modifyPackage(Request $shipmentRequest)
    {
        $originCountry = $shipmentRequest->getShipperAddressCountryCode();
        $destinationCountry = $shipmentRequest->getRecipientAddressCountryCode();

        // load applicable products for the current route
        $applicableProducts = $this->shippingProducts->getShippingProducts($originCountry, $destinationCountry);

        // check if defaults applicable to the current route are configured
        $defaults = array_intersect_key(
            $this->shippingProducts->getDefaultProducts($originCountry),
            $applicableProducts
        );

        $defaultProduct = current($defaults);
        $applicableProductCodes = current($applicableProducts);
        if (!in_array($defaultProduct, $applicableProductCodes, true)) {
            $message = __(
                'The product %1 is not valid for the route %2-%3.',
                $defaultProduct,
                $originCountry,
                $destinationCountry
            );
            throw new LocalizedException($message);
        }

        $packages = [];
        foreach ($shipmentRequest->getData('packages') as $packageId => $package) {
            $package['params']['shipping_product'] = $defaultProduct;
            $packages[$packageId] = $package;
        }

        // set all updated packages to request
        $shipmentRequest->setData('packages', $packages);

        // add current package's params to request (compare AbstractCarrierOnline::requestToShipment)
        $package = $packages[$shipmentRequest->getData('package_id')];
        $shipmentRequest->setData('package_params', $this->dataObjectFactory->create(['data' => $package['params']]));
    }

    /**
     * Add shipment request data using given shipment.
     *
     * The request modifier collects all additional data from defaults (config, product attributes)
     * during bulk label creation where no user input (packaging popup) is involved.
     *
     * @param Request $shipmentRequest
     * @throws LocalizedException
     */
    public function modify(Request $shipmentRequest)
    {
        // add carrier-agnostic data
        $this->coreModifier->modify($shipmentRequest);

        // add carrier-specific data
        $this->modifyPackage($shipmentRequest);
    }
}
