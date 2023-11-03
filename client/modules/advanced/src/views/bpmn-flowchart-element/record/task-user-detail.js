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

Espo.define('advanced:views/bpmn-flowchart-element/record/task-user-detail', 'advanced:views/bpmn-flowchart-element/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.controlFieldsVisibility();
        },

        controlFieldsVisibility: function () {
            this.hideField('targetUser');
            this.hideField('targetUser');
            this.hideField('targetUserPosition');

            this.setFieldNotRequired('targetUser');
            this.setFieldNotRequired('targetTeam');

            if (this.model.get('assignmentType') === 'specifiedUser') {
                this.showPanel('assignmentRule');
                this.showField('targetUser');
                this.setFieldRequired('targetUser');
            } else if ((this.model.get('assignmentType') || '').indexOf('rule:') === 0) {
                this.showPanel('assignmentRule');
                this.showField('targetTeam');
                this.showField('targetUserPosition');
                this.setFieldRequired('targetTeam');
            }
        }

    });

});