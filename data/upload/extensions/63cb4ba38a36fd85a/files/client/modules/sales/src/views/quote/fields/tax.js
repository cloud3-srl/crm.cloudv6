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

Espo.define('sales:views/quote/fields/tax', 'views/fields/link', function (Dep) {

    return Dep.extend({

        select: function (model) {
            var taxRate = model.get('rate');

            if (taxRate !== null) {
                this.model.set('taxRate', taxRate, {ui: true});
            }
            Dep.prototype.select.call(this, model);
        },

    });
});

