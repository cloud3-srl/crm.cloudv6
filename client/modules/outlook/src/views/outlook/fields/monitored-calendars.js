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

define('outlook:views/outlook/fields/monitored-calendars', 'views/fields/link-multiple', function (Dep) {

    return Dep.extend({

        nameHashName: null,

        idsName: null,

        nameHash: null,

        events: {
            'click [data-action="selectLink"]': function () {
                var self = this;
                this.notify('Please wait...');

                this.createView('modal', 'outlook:views/outlook/modals/select-calendar', {
                    calendars: this.model.calendarList
                }, function (view) {
                    self.notify(false);
                    view.render();
                    self.listenToOnce(view, 'select', function (calendar){
                        view.close();
                        self.addCalendar(calendar);
                    });
                });
            },
            'click [data-action="clearLink"]' : function (e) {
                this.clearLink(e);
            },
        },

        addCalendar: function (calendarId) {
            this.addLink(calendarId, this.model.calendarList[calendarId]);
        },

        afterRender: function () {
           this.$element = this.$el.find('input.main-element');
        },

        clearLink: function (e) {
            var id = $(e.currentTarget).data('id').toString();
            this.deleteLink(id);
        },

        setup: function () {
            this.nameHashName = this.name + 'Names';
            this.idsName = this.name + 'Ids';

            var self = this;

            this.ids = Espo.Utils.clone(this.model.get(this.idsName) || []);
            this.nameHash = Espo.Utils.clone(this.model.get(this.nameHashName) || {});

            this.listenTo(this.model, 'change:' + this.idsName, function () {
                this.ids = Espo.Utils.clone(this.model.get(this.idsName) || []);
                this.nameHash = Espo.Utils.clone(this.model.get(this.nameHashName) || {});
            }.bind(this));
        },

        afterRender: function () {
           this.renderLinks();
        },

        deleteLinkHtml: function (id) {
            this.$el.find('[data-id="'+id+'"]').remove();
        },

        addLinkHtml: function (id, name) {
            var conteiner = this.$el.find('.link-container');

            let escapedId = this.getHelper().escapeString(id);

            var $el = $('<div />').attr('data-id', id).addClass('list-group-item');
            $el.html(name + '&nbsp');
            $el.append('<a role="button" class="pull-right" data-id="' + escapedId + '" data-action="clearLink"><span class="fas fa-times"></a>');
            conteiner.append($el);

            return $el;
        },

        fetch: function () {
            var data = {};
            if (this.$el.is(':visible')) {
                data[this.idsName] = this.ids;
                data[this.nameHashName] = this.nameHash;
            } else {
                data[this.idsName] = null;
                data[this.nameHashName] = null;
            }
            return data;
        },

         validateRequired: function () {
            if (this.$el.is(':visible') && this.model.isRequired(this.name)) {
                if (this.model.get(this.idsName).length == 0) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.translate(this.name, 'fields', this.model.name));
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

    });
});
