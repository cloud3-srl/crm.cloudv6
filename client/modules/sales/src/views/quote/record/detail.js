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

define('sales:views/quote/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        stickButtonsFormBottomSelector: '.panel[data-name="items"]',

        setup: function () {
            Dep.prototype.setup.call(this);

            var printPdfAction = false;
            this.dropdownItemList.forEach(function (item) {
                if (item.name === 'printPdf') {
                    printPdfAction = true;
                }
            }, this);
            if (!printPdfAction) {
                this.dropdownItemList.push({
                    name: 'printPdf',
                    label: 'Print to PDF'
                });
            }

            this.dropdownItemList.push({
                name: 'composeEmail',
                label: 'Email PDF'
            });

        },

        actionPrintPdf: function () {
            this.createView('pdfTemplate', 'views/modals/select-template', {
                entityType: this.model.name
            }, function (view) {
                view.render();

                this.listenToOnce(view, 'select', function (model) {
                    window.open('?entryPoint=pdf&entityType='+this.model.name+'&entityId='+this.model.id+'&templateId=' + model.id, '_blank');
                }, this);
            }.bind(this));
        },

        actionComposeEmail: function () {
            this.createView('pdfTemplate', 'views/modals/select-template', {
                entityType: this.model.name
            }, function (view) {
                view.render();
                this.listenToOnce(view, 'select', function (model) {
                    this.notify('Loading...');
                    this.ajaxPostRequest(this.model.name + '/action/getAttributesForEmail', {
                        id: this.model.id,
                        templateId: model.id
                    }).done(function (attributes) {
                        var viewName = this.getMetadata().get('clientDefs.Email.modalViews.compose') || 'views/modals/compose-email';
                        this.createView('composeEmail', viewName, {
                            attributes: attributes,
                            keepAttachmentsOnSelectTemplate: true,
                            appendSignature: true,
                        }, function (view) {
                            view.render();
                            this.notify(false);
                        }, this);
                    }.bind(this));
                }, this);
            }, this);
        }

    });
});
