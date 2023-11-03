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
 * Copyright (C) 2015-2020 Letrium Ltd.
 * 
 * License ID: 2f687f2013bc552b8556948039df639e
 ***********************************************************************************/

define('sales:product-dynamic-handler', ['dynamic-handler'], function (Dep) {

    return Dep.extend({

        onChange: function (model, o) {
            if (!o.ui) return;
            this.calculatePrice();
        },

        calculatePrice: function (value, model, o) {
            var pricingType = this.model.get('pricingType');
            var pricingFactor = this.model.get('pricingFactor') || 0.0;

            var roundMultiplier = Math.pow(10, this.recordView.getConfig().get('currencyDecimalPlaces'));

            switch (pricingType) {
                case 'Same as List':
                    this.model.set('unitPrice', this.model.get('listPrice'));
                    this.model.set('unitPriceCurrency', this.model.get('listPriceCurrency'));
                    break;
                case 'Discount from List':
                    var currency = this.model.get('listPriceCurrency');
                    var value = this.model.get('listPrice');
                    value = value - value * pricingFactor / 100.0;
                    this.model.set({
                        'unitPrice': value,
                        'unitPriceCurrency': currency
                    });
                    break;
                case 'Markup over Cost':
                    var listCurrency = this.model.get('listPriceCurrency');
                    var costCurrency = this.model.get('costPriceCurrency');

                    var value = this.model.get('costPrice');
                    value = pricingFactor / 100.0 * value + value;

                    var baseCurrency = this.recordView.getConfig().get('baseCurrency');
                    var rates = this.recordView.getConfig().get('currencyRates') || {};

                    value = value * (rates[costCurrency] || 1.0);
                    value = value / (rates[listCurrency] || 1.0);

                    value = Math.round(value * roundMultiplier) / roundMultiplier;

                    this.model.set({
                        'unitPrice': value,
                        'unitPriceCurrency': listCurrency
                    });
                    break;
                case 'Profit Margin':
                    var listCurrency = this.model.get('listPriceCurrency');
                    var costCurrency = this.model.get('costPriceCurrency');

                    var value = this.model.get('costPrice');
                    value = value / (1 - pricingFactor / 100.0);

                    var baseCurrency = this.recordView.getConfig().get('baseCurrency');
                    var rates = this.recordView.getConfig().get('currencyRates') || {};

                    value = value * (rates[costCurrency] || 1.0);
                    value = value / (rates[listCurrency] || 1.0);

                    value = Math.round(value * roundMultiplier) / roundMultiplier;

                    this.model.set({
                        'unitPrice': value,
                        'unitPriceCurrency': listCurrency
                    });
                    break;
            }
        },

    });
});
