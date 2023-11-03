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

define('advanced:views/bpmn-flow-node/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        actionInterruptFlowNode: function (data) {
            this.actionRejectFlowNode(data);
        },

        actionRejectFlowNode: function (data) {
            var id = data.id;

            this.confirm(this.translate('confirmation', 'messages'), function () {
                Espo.Ajax.postRequest('BpmnProcess/action/rejectFlowNode', {
                    id: id,
                }).then(
                    function () {
                        this.collection.fetch().then(
                            function () {
                                Espo.Ui.success(this.translate('Done'));

                                if (this.collection.parentModel) {
                                    this.collection.parentModel.fetch();
                                }
                            }.bind(this)
                        );
                    }.bind(this)
                );
            }.bind(this));
        },

    });
});
