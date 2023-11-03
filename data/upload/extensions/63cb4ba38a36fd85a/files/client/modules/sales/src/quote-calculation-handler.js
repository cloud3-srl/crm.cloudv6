/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Sales Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/sales-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

Espo.define('sales:quote-calculation-handler', [], function () {

    var QuoteCalculationHandler = function (config) {
        this.config = config;
    }

    _.extend(QuoteCalculationHandler.prototype, {

        boundCurrencyFieldList: [
            'shippingCost',
            'taxAmount',
            'discountAmount',
            'amount',
            'preDiscountedAmount',
            'grandTotalAmount'
        ],

        boundCurrencyItemFieldList: ['listPrice', 'unitPrice', 'amount'],

        listenedAttributeList: ['taxRate', 'shippingCost'],

        listenedItemFieldList: ['name', 'quantity', 'unitPrice', 'listPrice', 'discount'],

        calculate: function (model) {
            var itemList = model.get('itemList') || [];

            var currency = model.get('amountCurrency');

            var amount = 0;

            itemList.forEach(function(item) {
                amount += item.amount || 0;
            }, this);

            var roundMultiplier = Math.pow(10, this.config.get('currencyDecimalPlaces'));

            amount = Math.round(amount * roundMultiplier) / roundMultiplier;
            model.set('amount', amount);

            var preDiscountedAmount = 0;
            itemList.forEach(function(item) {
                preDiscountedAmount += (item.listPrice || 0) * (item.quantity || 0);
            }, this);

            preDiscountedAmount = Math.round(preDiscountedAmount * roundMultiplier) / roundMultiplier;

            model.set({
                'preDiscountedAmount': preDiscountedAmount,
                'preDiscountedAmountCurrency': currency
            });

            var taxAmount = 0;
            itemList.forEach(function(item) {
                taxAmount += (item.amount || 0) * ((item.taxRate || 0) / 100.0);
            }, this);

            taxAmount = Math.round(taxAmount * roundMultiplier) / roundMultiplier;

            model.set({
                'taxAmount': taxAmount,
                'taxAmountCurrency': currency
            });

            var shippingCost = model.get('shippingCost') || 0;

            var discountAmount = preDiscountedAmount - amount;
            discountAmount = Math.round(discountAmount * roundMultiplier) / roundMultiplier;

            model.set({
                'discountAmount': discountAmount,
                'discountAmountCurrency': currency
            });

            var grandTotalAmount = amount + taxAmount + shippingCost;
            grandTotalAmount = Math.round(grandTotalAmount * roundMultiplier) / roundMultiplier;

            model.set({
                'grandTotalAmount': grandTotalAmount,
                'grandTotalAmountCurrency': currency
            });
        },

        calculateItem: function (model, field) {
            var quantity = model.get('quantity');
            var listPrice = model.get('listPrice');

            var unitPrice;
            var discount;

            var roundMultiplier = Math.pow(10, this.config.get('currencyDecimalPlaces'));

            if (field === 'discount') {
                discount = model.get('discount');
                unitPrice = listPrice - listPrice * (discount / 100);
                unitPrice = Math.round(unitPrice * roundMultiplier) / roundMultiplier;

                model.set('unitPrice', unitPrice);
            } else {
                unitPrice = model.get('unitPrice');
                discount = 0;
                if (listPrice) {
                    discount = ((listPrice - unitPrice) / listPrice) * 100;
                }
                discount = Math.round(discount * roundMultiplier) / roundMultiplier;

                model.set('discount', discount);
            }

            var amount = quantity * unitPrice;
            amount = Math.round(amount * roundMultiplier) / roundMultiplier;

            model.set('amount', amount);
        },

        selectProduct: function (model, product) {
            var sourcePrice;
            var sourceCurrency;
            var value;

            var roundMultiplier = Math.pow(10, this.config.get('currencyDecimalPlaces'));

            var targetCurrency = model.get('unitPriceCurrency');

            var baseCurrency = this.config.get('baseCurrency');
            var rates = this.config.get('currencyRates') || {};

            sourcePrice = product.get('unitPrice');
            sourceCurrency = product.get('unitPriceCurrency');

            var value = sourcePrice;
            value = value * (rates[sourceCurrency] || 1.0);
            value = value / (rates[targetCurrency] || 1.0);

            var unitTargetPrice = Math.round(value * roundMultiplier) / roundMultiplier;

            sourcePrice = product.get('listPrice');
            sourceCurrency = product.get('listPriceCurrency');

            value = sourcePrice;
            value = value * (rates[sourceCurrency] || 1.0);
            value = value / (rates[targetCurrency] || 1.0);

            var listTargetPrice = Math.round(value * roundMultiplier) / roundMultiplier;

            var discount = 0;
            if (listTargetPrice) {
                discount = ((listTargetPrice - unitTargetPrice) / listTargetPrice) * 100;
            }
            discount = Math.round(discount * roundMultiplier) / roundMultiplier;

            var attributes = {
                productId: product.id,
                productName: product.get('name'),
                name: product.get('name'),
                listPrice: listTargetPrice,
                listPriceCurrency: targetCurrency,
                unitPrice: unitTargetPrice,
                unitPriceCurrency: targetCurrency,
                unitWeight: product.get('weight') || null,
                discount: discount,
            };

            if (product.get('isTaxFree')) {
                attributes.taxRate = 0;
            }

            model.set(attributes);
        }
    });

    QuoteCalculationHandler.extend = Backbone.Router.extend;

    return QuoteCalculationHandler;
});
