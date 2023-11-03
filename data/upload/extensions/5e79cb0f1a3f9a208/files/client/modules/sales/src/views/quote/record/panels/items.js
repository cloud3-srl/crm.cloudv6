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

Espo.define('sales:views/quote/record/panels/items', 'views/record/panels/bottom', function (Dep) {

    return Dep.extend({

        template: 'sales:quote/record/panels/items',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('itemList', 'sales:views/quote/fields/item-list', {
                model: this.model,
                el: this.options.el + ' .field-itemList',
                defs: {
                    name: 'itemList'
                },
                mode: this.mode
            });
        },

        getFieldViews: function () {
            return this.getFields();
        },

        getFields: function () {
            var fields = {};
            fields.itemList = this.getView('itemList');
            return fields;
        },

    });
});

