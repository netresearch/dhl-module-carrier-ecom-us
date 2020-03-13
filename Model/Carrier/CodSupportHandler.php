<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace Dhl\EcomUs\Model\Carrier;

use Dhl\ShippingCore\Api\PaymentMethod\MethodAvailabilityInterface;
use Magento\Quote\Model\Quote;

/**
 * Class CodSupportHandler
 *
 * Declare whether the carrier supports Cash on Delivery shipping or not.
 *
 * @author Paul Siedler <paul.siedler@netresearch.de>
 * @link https://www.netresearch.de/
 */
class CodSupportHandler implements MethodAvailabilityInterface
{
    /**
     * @inheritdoc
     */
    public function isAvailable(Quote $quote): bool
    {
        return false;
    }
}
