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
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

Espo.define('sales:opportunity-calculation-handler', ['sales:quote-calculation-handler'], function (Dep) {

    return Dep.extend({

        boundCurrencyFieldList: [
            'amount'
        ],

        boundCurrencyItemFieldList: ['unitPrice', 'amount'],

        listenedAttributeList: [],

        listenedItemFieldList: ['name', 'quantity', 'unitPrice'],

        calculateAmount: function (model) {
            var amount = 0;
            var itemList = model.get('itemList') || [];
            itemList.forEach(function(item) {
                amount += item.amount || 0;
            }, this);

            amount = Math.round(amount * 100) / 100;

            model.set('amount', amount);
        },

        calculateItem: function (model) {
            var quantity = model.get('quantity');
            var unitPrice = model.get('unitPrice');

            var amount = quantity * unitPrice;
            amount = Math.round(amount * 100) / 100;

            model.set({
                amount: amount
            });
        },

        selectProduct: function (model, product) {
            var sourcePrice = product.get('unitPrice');
            var sourceCurrency = product.get('unitPriceCurrency');
            var targetCurrency = model.get('unitPriceCurrency');

            var baseCurrency = this.config.get('baseCurrency');
            var rates = this.config.get('currencyRates') || {};

            var value = sourcePrice;
            value = value * (rates[sourceCurrency] || 1.0);
            value = value / (rates[targetCurrency] || 1.0);

            var targetPrice = Math.round(value * 100) / 100;

            model.set({
                productId: product.id,
                productName: product.get('name'),
                name: product.get('name'),
                unitPrice: targetPrice,
                unitPriceCurrency: targetCurrency
            });
        }

    });
});
