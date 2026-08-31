/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'uiComponent',
    'ko',
    'Magento_Customer/js/customer-data',
    'mage/translate'
], function (Component, ko, customerData, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Luma_FreeShippingBar/minicart/free-shipping-bar'
        },

        /**
         * Bind the component to the "cart" customer data section so that it is
         * refreshed whenever the cart changes (add to cart, qty change, removal).
         *
         * @returns {Object} Chainable.
         */
        initialize: function () {
            this._super();

            this.cart = customerData.get('cart');

            this.progressData = ko.computed(function () {
                return this.cart().free_shipping || {};
            }, this);

            this.isVisible = ko.computed(function () {
                return !!this.progressData().active && Number(this.cart().summary_count) > 0;
            }, this);

            this.percent = ko.computed(function () {
                return Math.max(0, Math.min(100, Number(this.progressData().progress) || 0));
            }, this);

            this.isQualified = ko.computed(function () {
                return !!this.progressData().qualified || this.percent() >= 100;
            }, this);

            this.barWidth = ko.computed(function () {
                return this.percent() + '%';
            }, this);

            this.message = ko.computed(function () {
                if (this.isQualified()) {
                    return $t('You\'ve unlocked free shipping!');
                }

                return $t('Add %1 more for free shipping!')
                    .replace('%1', this.progressData().remaining_formatted || '');
            }, this);

            this.ariaLabel = ko.computed(function () {
                return $t('Free shipping progress: %1% complete').replace('%1', this.percent());
            }, this);

            return this;
        }
    });
});
