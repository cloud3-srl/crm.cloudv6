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

Espo.define('sales:views/sales-order/record/panels/invoices', 'views/record/panels/relationship', function (Dep) {

    return Dep.extend({

        actionCreateRelatedInvoice: function () {
            this.notify('Loading...');
            Espo.Ajax.getRequest('Invoice/action/getAttributesFromSalesOrder', {
                salesOrderId: this.model.id
            }).done(function (attributes) {
                var viewName = this.getMetadata().get('clientDefs.Invoice.modalViews.edit') || 'views/modals/edit';
                this.createView('quickCreate', viewName, {
                    scope: 'Invoice',
                    relate: {
                        model: this.model,
                        link: 'salesOrder',
                    },
                    attributes: attributes,
                }, function (view) {
                    view.render();
                    view.notify(false);
                    this.listenToOnce(view, 'after:save', function () {
                        this.collection.fetch();
                    }, this);
                }.bind(this));
            }.bind(this));
        },

    });
});
