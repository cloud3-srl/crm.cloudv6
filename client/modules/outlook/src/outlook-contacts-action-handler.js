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

define('outlook:outlook-contacts-action-handler', ['action-handler'], function (Dep) {

    return Dep.extend({

        actionPushToOutlook: function (data, e) {
            Espo.Ui.notify('...');
            Espo.Ajax.postRequest('OutlookContacts/action/push', {
                idList: [this.view.model.id],
                entityType: this.view.model.entityType,
            }).then(function (response) {
                if (response.count) {
                    Espo.Ui.success(this.view.translate('Done'));
                } else {
                    Espo.Ui.error(this.view.translate('Error'));
                }
            }.bind(this));
        },
    });
});
