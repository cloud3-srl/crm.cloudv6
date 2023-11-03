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

define('outlook:views/outlook/fields/contact-folder', 'views/fields/link', function (Dep) {

    return Dep.extend({

        autocompleteDisabled: true,

        events: {
            'click [data-action="selectLink"]': function (e) {
                e.stopPropagation();
                e.preventDefault();

                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                this.createView('modal', 'outlook:views/outlook/modals/select-contact-folder', {
                }, function (view) {
                    Espo.Ui.notify(false);
                    view.render();
                    this.listenToOnce(view, 'select', function (id, name){
                        view.close();
                        this.setFolder(id, name);
                    }, this);
                });
            } ,
            'click [data-action="clearLink"]' : function (e) {
                this.clearLink(e);
            },
        },

        setup: function () {
            this.nameName = this.name + 'Name';
            this.idName = this.name + 'Id';
        },

        setFolder: function (id, name) {
            this.$elementName.val(name);
            this.$elementId.val(id);
            this.trigger('change');
        },

    });
});
