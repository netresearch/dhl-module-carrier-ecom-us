/**
 * See LICENSE.md for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        'Dhl_EcomUs/js/model/shipping-rates-validator',
        'Dhl_EcomUs/js/model/shipping-rates-validation-rules'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        shippingRatesValidator,
        shippingRatesValidationRules
    ) {
        'use strict';

        defaultShippingRatesValidator.registerValidator('dhlecomus', shippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('dhlecomus', shippingRatesValidationRules);

        return Component;
    }
);
