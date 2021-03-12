<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace Dhl\EcomUs\Model\ShippingSettings\ShippingOption;

/**
 * Carrier code, option code, and input code definitions for use in the shipping_settings.xml files.
 */
class Codes
{
    // package details
    public const PACKAGE_INPUT_BILLING_REF = 'billingReference';
    public const PACKAGE_INPUT_DG_CATEGORY = 'dgCategory';
    public const PACKAGE_INPUT_DESCRIPTION = 'description';

    // package customs
    public const PACKAGE_INPUT_TERMS_OF_TRADE = 'termsOfTrade';
}
