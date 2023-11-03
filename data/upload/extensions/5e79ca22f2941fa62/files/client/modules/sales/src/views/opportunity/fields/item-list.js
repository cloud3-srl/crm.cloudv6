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

Espo.define('sales:views/opportunity/fields/item-list', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        detailTemplate: 'sales:opportunity/fields/item-list/detail',

        listTemplate: 'sales:opportunity/fields/item-list/detail',

        editTemplate: 'sales:opportunity/fields/item-list/edit',

        events: {
            'click [data-action="removeItem"]': function (e) {
                var id = $(e.currentTarget).attr('data-id');
                this.removeItem(id);
            },
            'click [data-action="addItem"]': function (e) {
                this.addItem();
            }
        },

        data: function () {
            return {
                showCurrency: (this.model.get('itemList') || []).length > 0,
                isEmpty: (this.model.get('itemList') || []).length === 0,
                mode: this.mode
            };
        },

        setMode: function (mode) {
            Dep.prototype.setMode.call(this, mode);
            if (this.isRendered()) {
                this.getView('currencyField').setMode(mode);
            }
        },

        getAttributeList: function () {
            return ['itemList'];
        },

        generateId: function () {
            return Math.random().toString(36).substr(2, 10);
        },

        setup: function () {
            var itemList = this.model.get('itemList') || [];

            var calculationHandlerClassName =
                this.getMetadata().get(['clientDefs', this.model.name, 'calculationHandler']) ||
                'sales:opportunity-calculation-handler';

            this.wait(true);

            Espo.require(calculationHandlerClassName, function (CalculationHandler) {
                this.calculationHandler = new CalculationHandler(this.getConfig());

                this.listenTo(this.model, 'change:amountCurrency', function (model, v, o) {
                    if (!o.ui) return;
                    var currency = this.model.get('amountCurrency');
                    var itemList = Espo.Utils.cloneDeep(this.model.get('itemList') || []);


                    this.calculationHandler.boundCurrencyItemFieldList.forEach(function (field) {
                        itemList.forEach(function (item) {
                            item[field + 'Currency'] = currency;
                        }, this);
                    }, this);

                    this.calculationHandler.boundCurrencyFieldList.forEach(function (field) {
                        this.model.set(field + 'Currency', currency);
                    }, this);

                    this.model.set('itemList', itemList);
                }, this);

                this.calculationHandler.listenedAttributeList.forEach(function (attribute) {
                    this.listenTo(this.model, 'change:' + attribute, function (model, v, o) {
                        if (!o.ui) return;
                        this.calculateAmount();
                    }, this);
                }, this);

                this.currencyModel = new Model();

                this.currencyModel.set('currency', this.model.get('amountCurrency') || this.getPreferences().get('defaultCurrency') || this.getConfig().get('defaultCurrency'));
                this.createView('currencyField', 'views/fields/enum', {
                    el: this.options.el + ' .field[data-name="total-currency"]',
                    model: this.currencyModel,
                    mode: this.mode,
                    inlineEditDisabled: true,
                    defs: {
                        name: 'currency',
                        params: {
                            options: this.getConfig().get('currencyList') || []
                        }
                    }
                });

                this.listenTo(this.model, 'change:amountCurrency', function () {
                    this.currencyModel.set('currency', this.model.get('amountCurrency'), {preventLoop: true});
                }, this);

                this.listenTo(this.currencyModel, 'change:currency', function (model, v, o) {
                    if (o && o.preventLoop) return;
                    this.model.set('amountCurrency', model.get('currency'), {ui: true});
                }, this);

                this.wait(false);

            }.bind(this));
        },

        handleCurrencyField: function () {
            var recordView = this.getParentView().getParentView();

            var itemList = this.model.get('itemList') || [];

            if (itemList.length) {
                this.showCurrencyField();
                if (recordView.setFieldReadOnly) {
                    recordView.setFieldReadOnly('amount');
                }
            } else {
                if (recordView.setFieldNotReadOnly) {
                    recordView.setFieldNotReadOnly('amount');
                }
                this.hideCurrencyField();
            }
        },

        showCurrencyField: function () {

            this.$el.find('.field-currency').removeClass('hidden');
        },

        hideCurrencyField: function () {
            this.$el.find('.field-currency').addClass('hidden');
        },

        afterRender: function () {
            this.$container = this.$el.find('.container');

            this.handleCurrencyField();

            var itemListViewName =
                this.getMetadata().get(['clientDefs', this.model.name, 'recordViews', 'itemList'])
                ||
                'sales:views/opportunity/record/item-list';

            this.createView('itemList', itemListViewName, {
                el: this.options.el + ' .item-list-container',
                model: this.model,
                mode: this.mode,
                calculationHandler: this.calculationHandler,
                notToRender: true,
            }, function (view) {
                this.listenTo(view, 'after:render', function () {
                    if (this.mode == 'edit') {
                        this.$el.find('.item-list-internal-container').sortable({
                            handle: '.drag-icon',
                            stop: function () {
                                var idList = [];
                                this.$el.find('.item-list-internal-container').children().each(function (i, el) {
                                    idList.push($(el).attr('data-id'));
                                });
                                this.reOrder(idList);
                            }.bind(this),
                        });
                    }
                }, this);
                view.render();

                this.listenTo(view, 'change', function () {
                    this.trigger('change');
                    this.calculateAmount();
                }, this);
            }.bind(this));
        },

        fetchItemList: function () {
            return (this.getView('itemList').fetch() || {}).itemList || [];
        },

        fetch: function () {
            var data = {};
            if (this.hasView('currencyField')) {
                data.amountCurrency = this.getView('currencyField').fetch().currency;
            }
            data.itemList = this.fetchItemList();
            return data;
        },

        addItem: function () {
            var currency = this.model.get('amountCurrency');
            if (!currency) {
                if (this.getFieldView('currency')) {
                    currency = this.getFieldView('currency').fetch().currency;
                }
            }

            var id = 'cid' + this.generateId();
            var data = {
                id: id,
                quantity: 1,
                unitPriceCurrency: currency
            };
            var itemList = Espo.Utils.clone(this.fetchItemList());
            itemList.push(data);
            this.model.set('itemList', itemList);
            this.calculateAmount();
        },

        removeItem: function (id) {
            var itemList = Espo.Utils.clone(this.fetchItemList());
            var index = -1;
            itemList.forEach(function (item, i) {
                if (item.id === id) {
                    index = i;
                }
            }, this);

            if (~index) {
                itemList.splice(index, 1);
            }
            this.model.set('itemList', itemList);
            this.calculateAmount();
        },

        calculateAmount: function () {
            this.calculationHandler.calculate(this.model);
        },

        reOrder: function (idList) {
            var orderedItemList = [];
            var itemList = this.model.get('itemList') || [];

            idList.forEach(function (id) {
                itemList.forEach(function (item) {
                    if (item.id === id) {
                        orderedItemList.push(item);
                    }
                }, this);
            }, this);

            this.model.set('itemList', orderedItemList);
        },

        getFieldView: function (name) {
            return this.getView(name + 'Field');
        }

    });
});
