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

define('outlook:views/outlook/fields/main-calendar', 'views/fields/link', function (Dep) {

    return Dep.extend({

        nameName: null,

        idName: null,

        data: function () {
            return _.extend({
                idName: this.idName,
                nameName: this.nameName,
                idValue: this.model.get(this.idName),
                nameValue: this.model.get(this.nameName),
            }, Dep.prototype.data.call(this));
        },

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
            } ,
            'click [data-action="clearLink"]' : function (e) {
                    this.clearLink(e);
                },
        },


        setup: function () {
            this.nameName = this.name + 'Name';
            this.idName = this.name + 'Id';
        },

        clearLink: function(e) {
            this.$elementName.val('');
            this.$elementId.val('');
            this.trigger('change');
        },

        afterRender: function () {
                this.$elementId = this.$el.find('input[name="' + this.idName + '"]');
                this.$elementName = this.$el.find('input[name="' + this.nameName + '"]');

                if (!this.$elementId.length) {
                    this.$elementId = this.$el.find('input[data-name="' + this.idName + '"]');
                }
                if (!this.$elementName.length) {
                    this.$elementName = this.$el.find('input[data-name="' + this.nameName + '"]');
                }

                this.$elementName.on('change', function () {
                    if (this.$elementName.val() == '') {
                        this.$elementName.val('');
                        this.$elementId.val('');
                        this.trigger('change');
                    }
                }.bind(this));
        },

        addCalendar: function (calendarId) {
            this.$elementName.val(this.model.calendarList[calendarId]);
            this.$elementId.val(calendarId);
            this.trigger('change');
        },

        fetch: function () {
            var data = {};
            if (this.$el.is(':visible')) {
                data[this.nameName] = this.$elementName.val() || null;
                data[this.idName] = this.$elementId.val() || null;
            } else {
                data[this.nameName] = null;
                data[this.idName] = null;
            }
            return data;
        },

        validateRequired: function () {
            if (this.$el.is(':visible') && (this.params.required || this.model.isRequired(this.name))) {
                if (this.model.get(this.idName) == null) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.translate(this.name, 'fields', this.model.name));
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

    });
});
