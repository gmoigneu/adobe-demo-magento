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
            template: 'Luma_FreeShippingProgress/minicart/free-shipping-progress'
        },

        /**
         * Bind the component to the "cart" customer data section.
         *
         * @returns {Object} Chainable.
         */
        initialize: function () {
            this._super();

            this.cart = customerData.get('cart');

            /**
             * Progress payload injected into the cart section, with defensive
             * defaults so a stale cached section simply hides the bar.
             *
             * @returns {Object}
             */
            this.progress = ko.computed(function () {
                return this.cart() && this.cart()['free_shipping_progress'] || {};
            }, this);

            /**
             * Whether the bar should be rendered at all.
             *
             * @returns {Boolean}
             */
            this.isVisible = ko.computed(function () {
                var cart = this.cart() || {};

                return !!this.progress().enabled && Number(cart['summary_count']) > 0;
            }, this);

            /**
             * Whether the cart already qualifies for free shipping.
             *
             * @returns {Boolean}
             */
            this.qualified = ko.computed(function () {
                return !!this.progress().qualified;
            }, this);

            /**
             * Fill of the bar, in percent (0-100).
             *
             * @returns {Number}
             */
            this.percent = ko.computed(function () {
                var percent = Number(this.progress().percent);

                if (isNaN(percent)) {
                    return 0;
                }

                return Math.max(0, Math.min(100, Math.round(percent)));
            }, this);

            /**
             * Message shown next to the bar.
             *
             * @returns {String}
             */
            this.message = ko.computed(function () {
                if (this.qualified()) {
                    return $t('You\'ve unlocked free shipping!');
                }

                return $t('Add %1 more for free shipping!')
                    .replace('%1', this.progress()['remaining_formatted'] || '');
            }, this);

            this.label = $t('Free shipping progress');

            return this;
        }
    });
});
