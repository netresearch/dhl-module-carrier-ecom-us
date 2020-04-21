<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\Provider\Controller\SaveShipment;

use Dhl\EcomUs\Util\ShippingProducts;
use Dhl\ShippingCore\Api\ConfigInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Prepare POST data as sent to the `dhl/order_shipment/save` controller
 */
class PackagingDataProvider
{
    /**
     * @param OrderInterface|Order $order
     * @return string|null
     */
    private static function getShippingProduct(OrderInterface $order)
    {
        /** @var ConfigInterface $config */
        $config = Bootstrap::getObjectManager()->get(ConfigInterface::class);
        /** @var ShippingProducts $shippingProducts */
        $shippingProducts = Bootstrap::getObjectManager()->get(ShippingProducts::class);

        $originCountry = $config->getOriginCountry($order->getStoreId());

        $applicableProducts = $shippingProducts->getShippingProducts(
            $originCountry,
            $order->getShippingAddress()->getCountryId()
        );

        $defaultProducts = $shippingProducts->getDefaultProducts($originCountry, $order->getStoreId());
        $routeProducts = array_intersect_key($defaultProducts, $applicableProducts);
        return array_shift($routeProducts);
    }

    /**
     * Pack all order items into one package. Cross-border data is omitted.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function singlePackageDomestic(OrderInterface $order)
    {
        $productCode = self::getShippingProduct($order);
        $package = [
            'packageId' => '1',
            'items' => [],
            'package' => [
                'packageDetails' => [
                    'productCode' => $productCode,
                    'packagingWeight' => '0.33',
                    'weight' => '0',
                    'weightUnit' => \Zend_Measure_Weight::POUND,
                    'width' => '8',
                    'height' => '8',
                    'length' => '12',
                    'sizeUnit' => \Zend_Measure_Length::INCH,
                ]
            ]
        ];

        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];

            $package['items'][$orderItem->getItemId()]['details'] = $itemDetails;

            $rowWeight = $orderItem->getWeight() * $orderItem->getQtyOrdered();
            $package['package']['packageDetails']['weight'] += $rowWeight;
        }

        return [$package];
    }

    /**
     * Pack each order item into an individual package. Cross-border data is omitted.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function multiPackageDomestic(OrderInterface $order)
    {
        $packages = [];
        $productCode = self::getShippingProduct($order);

        $packageId = 1;
        foreach ($order->getItems() as $orderItem) {
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];

            $packageDetails =  [
                'productCode' => $productCode,
                'packagingWeight' => '0.33',
                'weight' => $orderItem->getWeight() * $orderItem->getQtyOrdered(),
                'weightUnit' => \Zend_Measure_Weight::POUND,
                'width' => '8',
                'height' => '8',
                'length' => '12',
                'sizeUnit' => \Zend_Measure_Length::INCH,
            ];

            $packages[] = [
                'packageId' => $packageId,
                'items' => [
                    $orderItem->getItemId() => ['details' => $itemDetails]
                ],
                'package' => [
                    'packageDetails' => $packageDetails,
                ]
            ];

            $packageId++;
        }

        return $packages;
    }

    /**
     * Pack all order items into one package.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function singlePackageCrossBorder(OrderInterface $order)
    {
        $productCode = self::getShippingProduct($order);
        $package = [
            'packageId' => '1',
            'items' => [],
            'package' => [
                'packageDetails' => [
                    'productCode' => $productCode,
                    'packagingWeight' => '0.33',
                    'weight' => '0',
                    'weightUnit' => \Zend_Measure_Weight::POUND,
                    'width' => '8',
                    'height' => '8',
                    'length' => '12',
                    'sizeUnit' => \Zend_Measure_Length::INCH,
                ],
                'packageCustoms' => [
                    'customsValue' => '45',
                    'termsOfTrade' => 'DDU',
                    'dgCategory' => '01',
                    'exportDescription' => 'package export description',
                    'contentType' => 'OTHER',
                    'explanation' =>  'adasdads'
                ]
            ]
        ];

        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];
            $package['items'][$orderItem->getItemId()]['details'] = $itemDetails;

            $itemCustoms = [
                'customsValue' => $orderItem->getPrice(),
                'hsCode' =>  '12345' . $orderItem->getItemId(),
                'countryOfOrigin' => 'IN',
                'exportDescription' => 'item export description'
            ];

            $package['items'][$orderItem->getItemId()]['itemCustoms'] = $itemCustoms;
            $rowWeight = $orderItem->getWeight() * $orderItem->getQtyOrdered();
            $package['package']['packageDetails']['weight'] += $rowWeight;
        }

        return [$package];
    }

    /**
     * Pack each order item into an individual package.
     *
     * @param OrderInterface $order
     * @return mixed[]
     */
    public static function multiPackageCrossBorder(OrderInterface $order)
    {
        $packages = [];
        $productCode = self::getShippingProduct($order);

        $packageId = 1;
        foreach ($order->getItems() as $orderItem) {
            $itemDetails = [
                'qty' => $orderItem->getQtyOrdered(),
                'qtyToShip' => $orderItem->getQtyOrdered(),
                'weight' => $orderItem->getWeight(),
                'productId' => $orderItem->getProductId(),
                'productName' => $orderItem->getName(),
                'price' => $orderItem->getBasePrice(),
            ];

            $itemCustoms = [
                'customsValue' => $orderItem->getPrice(),
                'hsCode' =>  '12345' . $orderItem->getItemId(),
                'countryOfOrigin' => 'CN',
                'exportDescription' => 'item export description'
            ];

            $packageDetails =  [
                'productCode' => $productCode,
                'packagingWeight' => '0.33',
                'weight' => $orderItem->getWeight() * $orderItem->getQtyOrdered(),
                'weightUnit' => \Zend_Measure_Weight::POUND,
                'width' => '8',
                'height' => '8',
                'length' => '12',
                'sizeUnit' => \Zend_Measure_Length::INCH,
            ];

            $packageCustoms = [
                'customsValue' => '45',
                'termsOfTrade' => 'DDU',
                'dgCategory' => '01',
                'exportDescription' => 'package export description',
                'contentType' => 'OTHER',
                'explanation' => 'adasdads'
            ];

            $packages[] = [
                'packageId' => $packageId,
                'items' => [
                    $orderItem->getItemId() => [
                        'details' => $itemDetails,
                        'itemCustoms' => $itemCustoms
                    ]
                ],
                'package' => [
                    'packageDetails' => $packageDetails,
                    'packageCustoms' => $packageCustoms
                ]
            ];

            $packageId++;
        }

        return $packages;
    }
}
