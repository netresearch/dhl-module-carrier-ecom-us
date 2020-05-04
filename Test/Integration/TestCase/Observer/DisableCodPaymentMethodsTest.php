<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Test\Integration\TestCase\Observer;

use Dhl\ShippingCore\Observer\DisableCodPaymentMethods;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Event\InvokerInterface;
use Magento\Framework\Event\Observer;
use Magento\OfflinePayments\Model\Cashondelivery;
use Magento\OfflinePayments\Model\Checkmo;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductBuilder;
use TddWizard\Fixtures\Catalog\ProductFixture;
use TddWizard\Fixtures\Catalog\ProductFixtureRollback;
use TddWizard\Fixtures\Checkout\CartBuilder;
use TddWizard\Fixtures\Customer\AddressBuilder;
use TddWizard\Fixtures\Customer\CustomerBuilder;
use TddWizard\Fixtures\Customer\CustomerFixture;
use TddWizard\Fixtures\Customer\CustomerFixtureRollback;

/**
 * @magentoAppArea frontend
 */
class DisableCodPaymentMethodsTest extends TestCase
{
    /**
     * @var ProductFixture
     */
    private static $productFixture;

    /**
     * @var CustomerFixture
     */
    private static $customerFixture;

    /**
     * @var Cart
     */
    private static $cart;

    /**
     * @var InvokerInterface
     */
    private $invoker;

    /**
     * @var Observer
     */
    private $observer;

    /**
     * @var string[]
     */
    private $observerConfig;

    /**
     * Prepare invoker, observer and observer config.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->invoker = Bootstrap::getObjectManager()->get(InvokerInterface::class);
        $this->observer = Bootstrap::getObjectManager()->get(Observer::class);
        $this->observerConfig = [
            'instance' => DisableCodPaymentMethods::class,
            'name' => 'dhlgw_disable_cod_payment',
        ];
    }

    /**
     * COD gets disabled after observer ran through, others remain the same.
     *
     * @return string[][]|bool[][]
     */
    public function dataProvider()
    {
        return [
            'cod_gets_disabled' => [Cashondelivery::class, true, false],
            'cod_remains_disabled' => [Cashondelivery::class, false, false],
            'checkmo_remains_enabled' => [Checkmo::class, true, true],
            'checkmo_remains_disabled' => [Checkmo::class, false, false],
        ];
    }

    /**
     * Set up data fixture.
     *
     * @throws \Exception
     */
    public static function createQuoteFixture()
    {
        /** @var AddressRepositoryInterface $customerAddressRepository */
        $customerAddressRepository = Bootstrap::getObjectManager()->get(AddressRepositoryInterface::class);
        $shippingMethod = 'dhlecomus_flatrate';

        // prepare checkout
        self::$productFixture = new ProductFixture(ProductBuilder::aSimpleProduct()->build());
        self::$customerFixture = new CustomerFixture(
            CustomerBuilder::aCustomer()
                ->withAddresses(AddressBuilder::anAddress(null, 'en_US')->asDefaultBilling()->asDefaultShipping())
                ->build()
        );
        self::$customerFixture->login();

        self::$cart = CartBuilder::forCurrentSession()->withSimpleProduct(self::$productFixture->getSku())->build();

        // select customer's default shipping address in shipping step
        $customerAddressId = self::$cart->getCustomerSession()->getCustomer()->getDefaultShippingAddress()->getId();
        $shippingAddress = self::$cart->getQuote()->getShippingAddress();
        $shippingAddress->importCustomerAddressData($customerAddressRepository->getById($customerAddressId));
        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();
        $shippingAddress->setShippingMethod($shippingMethod);
        $shippingAddress->save();
    }

    public static function createQuoteFixtureRollback()
    {
        try {
            /** @var Session $session */
            $session = Bootstrap::getObjectManager()->get(Session::class);
            $session->logout();

            CustomerFixtureRollback::create()->execute(self::$customerFixture);
            ProductFixtureRollback::create()->execute(self::$productFixture);
            self::$cart->getQuote()->delete();
        } catch (\Exception $exception) {
            if (
                isset($_SERVER['argv'])
                && is_array($_SERVER['argv'])
                && in_array('--verbose', $_SERVER['argv'], true)
            ) {
                $message = sprintf("Error during rollback: %s%s", $exception->getMessage(), PHP_EOL);
                register_shutdown_function('fwrite', STDERR, $message);
            }
        }
    }

    /**
     * - If COD method is selected and it was available before, then it must get disabled.
     * - If COD method is selected and it was unavailable before, then it must remain disabled.
     * - If no COD method is selected and it was available before, then it must remain enabled.
     * - If no COD method is selected and it was unavailable before, then it must remain disabled.
     *
     * @test
     * @dataProvider dataProvider
     * @magentoDataFixture createQuoteFixture
     * @magentoConfigFixture current_store dhlshippingsolutions/dhlglobalwebservices/cod_methods cashondelivery
     * @magentoConfigFixture current_store shipping/origin/country_id US
     *
     * @param string $methodClass Payment method
     * @param bool $before Payment method availability before observer
     * @param bool $after Payment method availability after observer
     */
    public function updateCodAvailability(string $methodClass, bool $before, bool $after)
    {
        $checkResult = new DataObject();
        $checkResult->setData('is_available', $before);

        $this->observer->setData(
            [
                'result' => $checkResult,
                'method_instance' => Bootstrap::getObjectManager()->create($methodClass),
                'quote' => self::$cart->getQuote()
            ]
        );

        $this->invoker->dispatch($this->observerConfig, $this->observer);

        self::assertSame($after, $checkResult->getData('is_available'));
    }
}
