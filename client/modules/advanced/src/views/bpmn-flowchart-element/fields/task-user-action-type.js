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

Espo.define('advanced:views/bpmn-flowchart-element/fields/task-user-action-type', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);
            var list = this.getMetadata().get(['entityDefs', 'BpmnUserTask', 'fields', 'actionType', 'options']) || [];
            this.params.options = Espo.Utils.clone(list);
        }

    });

});