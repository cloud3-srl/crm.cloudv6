/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: a3ea4219cf9c3e5dee57026de28a15c1
 ***********************************************************************************/

define('advanced:views/report/modals/edit-group-by', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:report/modals/edit-group-by',

        data: function () {
            return {

            };
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'apply',
                    label: 'Apply',
                    style: 'danger',
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: function (dialog) {
                        dialog.close();
                    }
                }
            ];

            var v1 = this.options.value[0] || '';
            var v2 = this.options.value[1] || '';

            v1 = v1.replace(/\t/g, '\r\n');
            v2 = v2.replace(/\t/g, '\r\n');

            this.headerHtml = this.translate('groupBy', 'fields', 'Report');

            this.once('close', () => {
                if (this.$entityType) {
                    this.$entityType.popover('destroy');
                }
            });

            var m = new Model();

            m.set({
                v1: v1,
                v2: v2,
            });

            let fieldView =  'views/fields/formula';
            let targetEntityType = null;
            let insertDisabled = true;

            if (this.complexExpressionFieldIsAvailable()) {
                fieldView = 'views/fields/complex-expression';
                targetEntityType = this.model.get('entityType');
                insertDisabled = false;
            }

            this.createView('v1', fieldView, {
                model: m,
                name: 'v1',
                el: this.getSelector() + ' .v1-container',
                mode: 'edit',
                insertDisabled: insertDisabled,
                height: 50,
                targetEntityType: targetEntityType,
            });

            this.createView('v2', fieldView, {
                model: m,
                name: 'v2',
                el: this.getSelector() + ' .v2-container',
                mode: 'edit',
                insertDisabled: insertDisabled,
                height: 50,
                targetEntityType: targetEntityType,
            });
        },

        actionApply: function () {
            var value = [];

            var v1 = this.getView('v1').fetch()['v1'] || '';
            var v2 = this.getView('v2').fetch()['v2'] || '';

            v1 = v1.replace(/(?:\r\n|\r|\n)/g, '\t');
            v2 = v2.replace(/(?:\r\n|\r|\n)/g, '\t');

            if (v1) {
                value.push(v1);
            }

            if (v2) {
                value.push(v2);
            }

            this.trigger('apply', value);

            this.remove();
        },

        complexExpressionFieldIsAvailable: function () {
            let version = this.getConfig().get('version');

            if (version === '@@version' || this._isVersionGraterThanOrEqual('7.0.9', version)) {
                return true;
            }

            return false;
        },

        _isVersionGraterThanOrEqual: function (version1, version2) {
            if (version1 === version2) {
                return true;
            }

            let parts1 = version1.split('.');
            let parts2 = version2.split('.');

            let length = parts2.length;

            if (length > 3) {
                length = 3;
            }

            for (let i = 0; i < length; i++) {
                let a = ~~parts2[i];
                let b = ~~parts1[i];

                if (a > b) {
                    return true;
                }

                if (a < b) {
                    return false;
                }
            }

            return false;
        },

    });
});
