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

Espo.define('sales:views/admin/field-manager/quote-item/fields/product-copy-field-list', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            var ignoreFieldList = [
                'name',
                'listPrice',
                'listPriceCurrency',
                'listPriceConverted',
                'unitPrice',
                'unitPriceCurrency',
                'unitPriceConverted',
                'createdAt',
                'modifiedAt',
                'createdBy',
                'modifiedBy',
            ];

            var itemFieldList = Object.keys(this.getMetadata().get(['entityDefs', this.model.scope, 'fields']) || []);
            this.params.options = Object.keys(this.getMetadata().get(['entityDefs', 'Product', 'fields']) || []).filter(function (item) {
                if (~ignoreFieldList.indexOf(item)) return;
                if (!~itemFieldList.indexOf(item)) return;
                return true;
            }, this);

            this.translatedOptions = {};
            this.params.options.forEach(function (item) {
                this.translatedOptions[item] = this.translate(item, 'fields', 'Product');
            }.bind(this));
        }

    });
});