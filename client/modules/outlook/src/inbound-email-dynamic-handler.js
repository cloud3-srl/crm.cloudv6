/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Outlook Integration
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/outlook-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: 26bfa1fab74a68212506685b1b343192
 ***********************************************************************************/

define('outlook:inbound-email-dynamic-handler', ['dynamic-handler'], function (Dep) {

    return Dep.extend({

        init: function () {

            var version = this.recordView.getConfig().get('version') || '';

            function cmp (a, b) {
                var pa = a.split('.');
                var pb = b.split('.');
                for (var i = 0; i < 3; i++) {
                    var na = Number(pa[i]);
                    var nb = Number(pb[i]);
                    if (na > nb) return 1;
                    if (nb > na) return -1;
                    if (!isNaN(na) && isNaN(nb)) return 1;
                    if (isNaN(na) && !isNaN(nb)) return -1;
                }
                return 0;
            };

            if (version !== 'dev' && version !== '@@version' && cmp(version, '5.9.3') === -1) {
                return;
            }

            this.control();

            this.recordView.listenTo(
                this.model, 'change', this.control.bind(this)
            );

            this.recordView.listenTo(this.recordView, 'after:set-edit-mode', this.control.bind(this));
            this.recordView.listenTo(this.recordView, 'after:set-detail-mode', this.control.bind(this));
        },

        control: function () {
            if (this.recordView.name === 'edit' || this.recordView.mode === 'edit') {
                this.recordView.hidePanel('outlook');
                return;
            }

            if (
                this.model.get('useImap') && this.model.get('host') && (
                    ~this.model.get('host').indexOf('office365.')
                )
                ||
                this.model.get('useSmtp') && this.model.get('smtpHost') && (
                    ~this.model.get('smtpHost').indexOf('office365.')
                )
            ) {
                this.recordView.showPanel('outlook');
            } else {
                this.recordView.hidePanel('outlook');
            }
        },

    });
});
