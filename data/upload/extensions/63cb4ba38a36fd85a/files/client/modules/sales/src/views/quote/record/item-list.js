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

Espo.define('sales:views/quote/record/item-list', ['views/base', 'collection'], function (Dep, Collection) {

    return Dep.extend({

        template: 'sales:quote/record/item-list',

        data: function () {
            var hideTaxRate = this.noTax && this.mode === 'detail';
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

            return {
                itemDataList: this.itemDataList,
                mode: this.mode,
                hideTaxRate: hideTaxRate,
                showRowActions: this.showRowActions,
                listLayout: listLayout,
                itemEntityType: this.itemEntityType
            };
        },

        events: {
            'click .action': function (e) {
                var $el = $(e.currentTarget);
                var action = $el.data('action');
                var method = 'action' + Espo.Utils.upperCaseFirst(action);
                if (typeof this[method] == 'function') {
                    var data = $el.data();
                    this[method](data, e);
                    e.preventDefault();
                }
            }
        },

        setup: function () {
            this.mode = this.options.mode;

            this.calculationHandler = this.options.calculationHandler;

            this.itemDataList = [];

            var itemList = this.model.get('itemList') || [];

            this.collection = new Collection();

            this.itemEntityType = this.model.name + 'Item';

            this.collection.name = this.itemEntityType;

            this.collection.total = itemList.length;

            this.wait(true);

            this.noTax = true;

            this.listenTo(this.collection, 'change', function (m) {
                itemList.forEach(function (item, i) {
                    if (item.id == m.id) {
                        itemList[i] = m.getClonedAttributes();
                    }
                }, this);
            }, this)

            itemList.forEach(function (item, i) {
                if (item.taxRate) {
                    this.noTax = false;
                }
            }, this);

            this.showRowActions = this.mode == 'detail' && this.getAcl().checkModel(this.model, 'read');
            this.aclEdit = this.getAcl().checkModel(this.model, 'edit');

            this.getHelper().layoutManager.get(this.itemEntityType, 'listItem', function (listLayout) {
                this.listLayout = Espo.Utils.cloneDeep(listLayout);
                this.listLayout.forEach(function (item) {
                    item.key = item.name + 'Field';
                    if (item.name == 'quantity') {
                        item.customLabel = this.translate('qty', 'fields', this.itemEntityType);
                    }
                }, this);

                this.getModelFactory().create(this.itemEntityType, function (modelSeed) {
                    itemList.forEach(function (item, i) {
                        var model = modelSeed.clone();
                        model.name = this.itemEntityType;

                        var id = item.id || 'cid' + i;
                        this.itemDataList.push({
                            num: i,
                            key: 'item-' + i,
                            id: id
                        });

                        model.set(item);
                        this.collection.push(model);
                        var viewName = this.getMetadata().get(['clientDefs', this.itemEntityType, 'recordViews', 'item']) ||
                            'sales:views/quote/record/item';

                        this.createView('item-' + i, viewName, {
                            el: this.options.el + ' .item-container[data-id="' + id + '"]',
                            model: model,
                            parentModel: this.model,
                            mode: this.mode,
                            noTax: this.noTax,
                            showRowActions: this.showRowActions,
                            aclEdit: this.aclEdit,
                            itemEntityType: this.itemEntityType,
                            listLayout: this.listLayout,
                            calculationHandler: this.calculationHandler
                        }, function (view) {
                            this.listenTo(view, 'change', function () {
                                this.trigger('change');
                            }, this);
                        }, this);

                        if (i == itemList.length - 1) {
                            this.wait(false);
                        }
                    }, this);

                    if (itemList.length === 0) {
                        this.wait(false);
                    }
                }, this);
            }.bind(this));
        },

        fetch: function () {
            var itemList = [];
            this.itemDataList.forEach(function (item) {
                var data = this.getView(item.key).fetch();
                data.id = data.id || item.id;
                itemList.push(data);
            }, this);
            return {
                itemList: itemList
            };
        },

        actionQuickView: function (data) {
            data = data || {};
            var id = data.id;
            if (!id) return;

            var model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }

            var scope = this.collection.name;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.detail') || 'views/modals/detail';

            this.notify('Loading...');
            this.createView('modal', viewName, {
                scope: scope,
                model: model,
                id: id
            }, function (view) {
                this.listenToOnce(view, 'after:render', function () {
                    Espo.Ui.notify(false);
                });
                view.render();

                this.listenToOnce(view, 'remove', function () {
                    this.clearView('modal');
                }, this);

                this.listenToOnce(view, 'after:edit-cancel', function () {
                    this.actionQuickView({id: view.model.id, scope: view.model.name});
                }, this);

                this.listenToOnce(view, 'after:save', function (m) {
                    var model = this.collection.get(m.id);
                    if (model) {
                        model.set(m.getClonedAttributes());
                    }
                    this.trigger('after:save', m);
                }, this);
            }, this);
        },

        actionQuickEdit: function (data) {
            data = data || {}
            var id = data.id;
            if (!id) return;

            var model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }
            if (!data.scope && !model) {
                return;
            }

            var scope = this.collection.name;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            this.notify('Loading...');
            this.createView('modal', viewName, {
                scope: scope,
                id: id,
                model: model,
                fullFormDisabled: data.noFullForm,
                returnUrl: '#' + this.model.name + '/view/' + this.model.id,
                returnDispatchParams: {
                    controller: this.model.name,
                    action: 'view',
                    options: {
                        id: this.model.id,
                        isReturn: true
                    }
                }
            }, function (view) {
                view.once('after:render', function () {
                    Espo.Ui.notify(false);
                });

                view.render();

                this.listenToOnce(view, 'remove', function () {
                    this.clearView('modal');
                }, this);

                this.listenToOnce(view, 'after:save', function (m) {
                    var model = this.collection.get(m.id);
                    if (model) {
                        model.set(m.getClonedAttributes());
                    }
                    this.trigger('after:save', m);
                }, this);
            }, this);
        }
    });
});
