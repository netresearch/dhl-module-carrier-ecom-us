<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\Carrier;

use Netresearch\ShippingCore\Api\PaymentMethod\MethodAvailabilityInterface;
use Magento\Quote\Model\Quote;

/**
 * Declare whether the carrier supports Cash on Delivery shipping or not.
 */
class CodSupportHandler implements MethodAvailabilityInterface
{
    public function isAvailable(Quote $quote): bool
    {
        return false;
    }
}
