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

Espo.define('advanced:views/bpmn-flowchart-element/record/task-send-message-detail', 'advanced:views/bpmn-flowchart-element/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.controlFieldsVisibility();
        },

        controlFieldsVisibility: function () {
            this.hideField('fromEmailAddress');
            this.hideField('toEmailAddress');
            this.hideField('replyToEmailAddress');
            this.hideField('toSpecifiedTeams');
            this.hideField('toSpecifiedUsers');
            this.hideField('toSpecifiedContacts');

            if (this.model.get('from') === 'specifiedEmailAddress') {
                this.showField('fromEmailAddress');
            }
            if (this.model.get('to') === 'specifiedEmailAddress') {
                this.showField('toEmailAddress');
            }
            if (this.model.get('replyTo') === 'specifiedEmailAddress') {
                this.showField('replyToEmailAddress');
            }
            if (this.model.get('to') === 'specifiedUsers') {
                this.showField('toSpecifiedUsers');
            }
            if (this.model.get('to') === 'specifiedTeams') {
                this.showField('toSpecifiedTeams');
            }
            if (this.model.get('to') === 'specifiedContacts') {
                this.showField('toSpecifiedContacts');
            }
        }

    });

});