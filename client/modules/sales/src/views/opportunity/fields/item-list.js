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

define('sales:views/opportunity/fields/item-list', ['views/fields/base', 'model'], function (Dep, Model) {

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
            },
            'keydown': function (e) {
                if (!Espo.Utils.getKeyFromKeyEvent) {
                    return;
                }

                if (this.mode !== 'edit') {
                    return;
                }

                if (this._preventShortcutSave) {
                    return;
                }

                let key = Espo.Utils.getKeyFromKeyEvent(e);

                let keyList = [
                    'Control+Enter',
                    'Control+Alt+Enter',
                    'Control+KeyS',
                ];

                if (!keyList.includes(key)) {
                    return;
                }

                e.stopPropagation();
                e.preventDefault();

                // Invoke fetching.
                $(e.target).trigger('change');

                this._preventShortcutSave = true;
                setTimeout(() => this._preventShortcutSave = false, 100);

                setTimeout(() => {
                    // Native event not captured by browsers.
                    let newEvent = jQuery.Event('keydown', {
                        code: e.code,
                        shiftKey: e.shiftKey,
                        altKey: e.altKey,
                        metaKey: e.metaKey,
                        ctrlKey: e.ctrlKey,
                        keyCode: e.keyCode,
                        which: e.which,
                        location: e.location,
                        repeat: e.repeat,
                        isComposing: e.isComposing,
                        charCode: e.charCode,
                    });

                    $(e.target).trigger(newEvent);
                }, 50);
            },
            'click [data-action="addProducts"]': function () {
                this.actionAddProducts();
            },
        },

        data: function () {
            return {
                showCurrency: (this.model.get('itemList') || []).length > 0,
                isEmpty: (this.model.get('itemList') || []).length === 0,
                mode: this.mode,
                showAddProducts: this.getAcl().checkScope('Product'),
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

            Espo.require(calculationHandlerClassName, CalculationHandler =>{
                this.calculationHandler = new CalculationHandler(this.getConfig());

                this.listenTo(this.model, 'change:amountCurrency', (model, v, o) => {
                    if (!o.ui) {
                        return;
                    }

                    var currency = this.model.get('amountCurrency');
                    var itemList = Espo.Utils.cloneDeep(this.model.get('itemList') || []);

                    this.calculationHandler.boundCurrencyItemFieldList.forEach(field => {
                        itemList.forEach(item => {
                            item[field + 'Currency'] = currency;
                        });
                    });

                    this.calculationHandler.boundCurrencyFieldList.forEach(field => {
                        this.model.set(field + 'Currency', currency);
                    });

                    this.model.set('itemList', itemList);
                });

                this.calculationHandler.listenedAttributeList.forEach(attribute => {
                    this.listenTo(this.model, 'change:' + attribute, (model, v, o) => {
                        if (!o.ui) {
                            return;
                        }

                        this.calculateAmount();
                    });
                });

                this.currencyModel = new Model();

                this.currencyModel.set('currency', this.model.get('amountCurrency') ||
                    this.getPreferences().get('defaultCurrency') || this.getConfig().get('defaultCurrency'));

                this.createView('currencyField', 'views/fields/enum', {
                    el: this.options.el + ' .field[data-name="total-currency"]',
                    model: this.currencyModel,
                    mode: this.mode,
                    inlineEditDisabled: true,
                    defs: {
                        name: 'currency',
                        params: {
                            options: this.getConfig().get('currencyList') || []
                        },
                    },
                });

                this.listenTo(this.model, 'change:amountCurrency', () => {
                    this.currencyModel.set('currency', this.model.get('amountCurrency'), {preventLoop: true});
                });

                this.listenTo(this.currencyModel, 'change:currency', (model, v, o) => {
                    if (o && o.preventLoop) {
                        return;
                    }

                    this.model.set('amountCurrency', model.get('currency'), {ui: true});
                });

                this.wait(false);
            });
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
                this.getMetadata().get(['clientDefs', this.model.name, 'recordViews', 'itemList']) ||
                'sales:views/opportunity/record/item-list';

            this.createView('itemList', itemListViewName, {
                el: this.options.el + ' .item-list-container',
                model: this.model,
                mode: this.mode,
                calculationHandler: this.calculationHandler,
                notToRender: true,
            }, (view) => {
                this.listenTo(view, 'after:render', () => {
                    if (this.mode === 'edit') {
                        this.$el.find('.item-list-internal-container').sortable({
                            handle: '.drag-icon',
                            stop: () => {
                                var idList = [];

                                this.$el.find('.item-list-internal-container').children().each((i, el) => {
                                    idList.push($(el).attr('data-id'));
                                });

                                this.reOrder(idList);
                            },
                        });
                    }
                });

                view.render();

                this.listenTo(view, 'change', () => {
                    this.trigger('change');
                    this.calculateAmount();
                });
            });
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

        getEmptyItem: function () {
            let currency = this.model.get('amountCurrency');

            if (!currency) {
                if (this.getFieldView('currency')) {
                    currency = this.getFieldView('currency').fetch().currency;
                }
            }

            let id = 'cid' + this.generateId();

            return {
                id: id,
                quantity: 1,
                unitPriceCurrency: currency,
            };
        },

        addItem: function () {
            let data = this.getEmptyItem();
            let itemList = Espo.Utils.clone(this.fetchItemList());
            itemList.push(data);

            this.model.set('itemList', itemList, {ui: true});

            this.reRender();
        },

        removeItem: function (id) {
            var itemList = Espo.Utils.clone(this.fetchItemList());
            var index = -1;

            itemList.forEach((item, i) => {
                if (item.id === id) {
                    index = i;
                }
            });

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

            idList.forEach(id => {
                itemList.forEach(item => {
                    if (item.id === id) {
                        orderedItemList.push(item);
                    }
                });
            });

            this.model.set('itemList', orderedItemList);
        },

        getFieldView: function (name) {
            return this.getView(name + 'Field');
        },

        actionAddProducts: function () {
            let view = this.getMetadata().get(['clientDefs', 'Product', 'modalViews', 'select']) ||
                'views/modals/select-records';

            Espo.Ui.notify(' ... ');

            this.createView('dialog', view, {
                multiple: true,
                createButton: false,
                scope: 'Product',
                primaryFilterName: 'available',
                forceSelectAllAttributes: true,
            })
                .then(view => {
                    view.render();

                    Espo.Ui.notify(false);

                    this.listenToOnce(view, 'select', list => {
                        this.addProducts(list);
                    });
                });
        },

        addProducts: function (list) {
            let itemList = Espo.Utils.clone(this.fetchItemList());

            list.forEach(product => {
                let itemModel = new Model();

                itemModel.set(this.getEmptyItem());

                // @todo Move to helper class.
                let copyFieldList = this.getMetadata()
                    .get(['entityDefs', this.model.entityType + 'Item', 'fields', 'product', 'copyFieldList']) || [];

                copyFieldList.forEach(field => {
                    this.getFieldManager()
                        .getEntityTypeFieldAttributeList('Product', field)
                        .forEach(attribute => {
                            itemModel.set(attribute, product.get(attribute));
                        });
                });

                this.calculationHandler.selectProduct(itemModel, product);
                this.calculationHandler.calculateItem(itemModel);

                itemList.push(itemModel.attributes);
            });

            this.model.set('itemList', itemList, {ui: true});

            this.reRender();
        },
    });
});
