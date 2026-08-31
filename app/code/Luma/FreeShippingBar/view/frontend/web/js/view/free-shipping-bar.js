/**
 * Copyright © Luma. All rights reserved.
 */
define([
    'ko',
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'Magento_Catalog/js/price-utils',
    'mage/translate'
], function (ko, Component, customerData, priceUtils, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Luma_FreeShippingBar/free-shipping-bar'
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();

            this.cart = customerData.get('cart');

            this.barData = ko.pureComputed(function () {
                return this.cart()['free_shipping_bar'] || {};
            }, this);

            this.visible = ko.pureComputed(function () {
                var data = this.barData();

                return !!data.enabled &&
                    data.threshold > 0 &&
                    (this.cart()['summary_count'] || 0) > 0;
            }, this);

            this.percent = ko.pureComputed(function () {
                var data = this.barData();

                if (!data.threshold) {
                    return 0;
                }

                return Math.min(100, Math.round(data.subtotal / data.threshold * 100));
            }, this);

            this.unlocked = ko.pureComputed(function () {
                return this.percent() >= 100;
            }, this);

            this.message = ko.pureComputed(function () {
                var data = this.barData(),
                    remaining;

                if (this.unlocked()) {
                    return $t('You\'ve unlocked free shipping!');
                }

                remaining = priceUtils.formatPrice(
                    Math.max(0, data.threshold - data.subtotal),
                    data['price_format']
                );

                return $t('Add %1 more for free shipping!').replace('%1', remaining);
            }, this);

            return this;
        }
    });
});
