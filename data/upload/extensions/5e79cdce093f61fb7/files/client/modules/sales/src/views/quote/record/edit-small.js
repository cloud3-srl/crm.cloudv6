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

Espo.define('sales:views/quote/record/edit-small', 'views/record/edit-small', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.handleAmountField();

            this.listenTo(this.model, 'change:itemList', function () {
                this.handleAmountField();
            }, this);
        },

        handleAmountField: function () {
            var itemList = this.model.get('itemList') || [];
            if (!itemList.length) {
                this.setFieldNotReadOnly('amount');
            } else {
                this.setFieldReadOnly('amount');
            }
        }

    });
});

