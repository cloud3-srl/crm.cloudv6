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

define('advanced:views/bpmn-flowchart/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        saveAndContinueEditingAction: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            var dataEntityTypeMap = {};

            if (!this.model.isNew()) {
                this.setFieldReadOnly('targetType');
            } else {
                this.controlFlowchartField();
                this.listenTo(this.model, 'change:targetType', function (model) {
                    var previousEntityType = model.previous('targetType');
                    var targetType = model.get('targetType');
                    dataEntityTypeMap[previousEntityType] = this.model.get('data');
                    var data = dataEntityTypeMap[targetType] || {
                        list: []
                    };
                    this.controlFlowchartField();
                    this.model.set('data', data);
                }, this);
            }
        },

        controlFlowchartField: function () {
            var targetType = this.model.get('targetType');
            if (targetType) {
                this.showField('flowchart');
            } else {
                this.hideField('flowchart');
            }
        },

    });
});