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

Espo.define('advanced:views/bpmn-flowchart-element/fields/task-send-message-reply-to', ['views/fields/enum', 'advanced:views/bpmn-flowchart-element/fields/task-send-message-from'], function (Dep, From) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            this.params.options = Espo.Utils.clone(this.params.options);

            if (this.getMetadata().get(['entityDefs', this.model.targetEntityType, 'fields', 'emailAddress', 'type']) === 'email') {
                this.params.options.push('targetEntity');
            }

            var linkOptionList = From.prototype.getLinkOptionList.call(this, false, true);
            linkOptionList.forEach(function (item) {
                this.params.options.push(item);
            }, this);

            From.prototype.translateOptions.call(this);
        }

    });

});