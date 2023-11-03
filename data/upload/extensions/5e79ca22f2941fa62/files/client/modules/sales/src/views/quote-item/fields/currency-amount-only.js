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

Espo.define('sales:views/quote-item/fields/currency-amount-only', 'views/fields/currency', function (Dep) {

    return Dep.extend({

        editTemplate: 'fields/float/edit',

        fetch: function () {
            var value = this.$element.val();
            value = this.parse(value);
            var data = {};
            data[this.name] = value;

            return data;
        }

    });
});
