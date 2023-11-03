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

Espo.define('sales:views/quote/record/item', 'views/base', function (Dep) {

    return Dep.extend({

        template: 'sales:quote/record/item',

        data: function () {
            var hideTaxRate = this.options.noTax && this.mode === 'detail';
            var listLayout = [];
            if (hideTaxRate) {
                this.listLayout.forEach(function (item) {
                    if (item.name === 'taxRate') {
                        return;
                    }
                    listLayout.push(item);
                }, this);
            } else {
                listLayout = this.listLayout;
            }

            listLayout = Espo.Utils.cloneDeep(listLayout);

            listLayout.forEach(function (item) {
                if (~this.readOnlyFieldList.indexOf(item.name)) {
                    item.isReadOnly = true;
                }
            }, this);

            return {
                id: this.model.id,
                mode: this.mode,
                hideTaxRate: hideTaxRate,
                showRowActions: this.options.showRowActions,
                listLayout: listLayout
            };
        },

        setup: function () {
            this.mode = this.options.mode;

            this.itemEntityType = this.options.itemEntityType;

            this.parentModel = this.options.parentModel;

            this.calculationHandler = this.options.calculationHandler;

            if (this.options.showRowActions) {
                this.createView('rowActions', 'views/record/row-actions/view-and-edit', {
                    model: this.model,
                    acl: {
                        edit: this.options.aclEdit,
                        read: true
                    }
                });
            }

            this.listLayout = this.options.listLayout;

            this.fieldViewNameMap = {
                name: 'sales:views/quote-item/fields/name'
            };

            this.fieldList = [];
            this.readOnlyFieldList = [];

            this.listLayout.forEach(function (item) {
                var name = item.name;
                var options = {};

                if (this.mode === 'detail') {
                    if (item.link) {
                        options.mode = 'listLink';
                    }
                }

                if (name === 'taxRate') {
                    if (this.mode === 'detail') {
                        if (this.options.noTax) {
                            return;
                        }
                        options.mode = 'list';
                    }
                }


                var type = this.model.getFieldType(name) || 'base';

                var customView = null;
                if (type === 'currency') {
                    customView = 'sales:views/quote-item/fields/currency-amount-only';
                    options.hideCurrency = true;
                }

                if (this.model.getFieldParam(name, 'readOnly') && !this.model.getFieldParam(name, 'itemNotReadOnly')) {
                    this.readOnlyFieldList.push(name);
                    options.mode = 'detail';
                }

                var view = item.view || this.fieldViewNameMap[item.name] || customView || this.model.getFieldParam(name, 'view');
                if (!view) {
                    view = this.getFieldManager().getViewName(type);
                }

                this.createField(name, view, options);

            }, this);

            this.createField('description', 'views/fields/text', {mode: this.mode === 'edit' ? 'edit' : 'list'});
        },

        getFieldView: function (name) {
            return this.getView(name + 'Field');
        },

        createField: function (name, view, options, params) {
            var o = {
                model: this.model,
                defs: {
                    name: name,
                    params: params || {}
                },
                mode: this.mode,
                el: this.options.el + ' .field[data-name="item-'+name+'"]',
                inlineEditDisabled: true,
                readOnlyDisabled: true,
                calculationHandler: this.options.calculationHandler
            };

            if (options) {
                for (var i in options) {
                    o[i] = options[i];
                }
            }

            this.createView(name + 'Field', view, o, function (view) {
                this.listenTo(view, 'change', function () {
                    setTimeout(function () {
                        this.trigger('change');
                    }.bind(this), 50);
                }, this);
            }.bind(this));

            this.fieldList.push(name);
        },

        afterRender: function () {
            if (this.getFieldView('listPrice')) {
                this.listenTo(this.getFieldView('listPrice'), 'change', function () {
                    if (!this.model.get('unitPrice') && this.model.get('unitPrice') !== 0) {
                        this.model.set('unitPrice', this.model.get('listPrice'));
                    }
                }, this);
            }

            this.options.calculationHandler.listenedItemFieldList.forEach(function (field) {
                if (this.getFieldView(field)) {
                    this.listenTo(this.getFieldView(field), 'change', function () {
                        this.calculateAmount(field);
                    }, this);
                }
            }, this);
        },

        calculateAmount: function (field) {
            var currency = this.parentModel.get('amountCurrency');
            this.calculationHandler.boundCurrencyItemFieldList.forEach(function (item) {
                this.model.set(item + 'Currency', currency);
            }, this);

            this.calculationHandler.calculateItem(this.model, field);
        },

        fetch: function () {
            var data = {
                id: this.model.id,
                quantity: this.model.get('quantity'),
                taxRate: this.model.get('taxRate') || 0,
                listPrice: this.model.get('listPrice'),
                listPriceCurrency: this.model.get('listPriceCurrency'),
                unitPrice: this.model.get('unitPrice'),
                unitPriceCurrency: this.model.get('unitPriceCurrency'),
                amount: this.model.get('amount'),
                amountCurrency: this.model.get('amountCurrency'),
                productId: this.model.get('productId') || null,
                productName: this.model.get('productName') || null,
                name: this.model.get('name'),
                description: this.model.get('description'),
                unitWeight: this.model.get('unitWeight') || null
            };

            for (var attribute in this.model.attributes) {
                if (!(attribute in data)) {
                    data[attribute] = this.model.attributes[attribute];
                }
            }

            return data;
        }

    });
});
