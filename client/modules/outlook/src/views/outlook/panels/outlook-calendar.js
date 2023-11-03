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

define('outlook:views/outlook/panels/outlook-calendar', 'view', function (Dep) {

    return Dep.extend({

        template: 'outlook:outlook/panel',

        productName: 'outlookCalendar',

        fieldList: [],

        calendarList: [],

        isBlocked: true,

        fields: null,

        setupFields: function () {
            var scopes = this.scopesMetadataDefs = this.getMetadata().get('scopes');
            var version = this.getConfig().get('version').split('.');

            var eventOptions = Object.keys(scopes).filter((scope) => {
                if (scope === 'Email') return;
                if (scopes[scope].disabled) return;
                if (!scopes[scope].object) return;
                if (!scopes[scope].entity) return;

                if (!scopes[scope].activity || !scopes[scope].calendar) {
                    return;
                }

                return true;
            })
            .sort(function (v1, v2) {
                 return this.translate(v1, 'scopeNames').localeCompare(this.translate(v2, 'scopeNames'));
            }.bind(this));

            this.fields = {
                calendarDirection: {
                    type: 'enum',
                    options: ["EspoToOutlook", "OutlookToEspo", "Both"],
                    default: 'Both',
                },
                calendarStartDate: {
                    required: true,
                    type: 'date'
                },
                calendarEntityTypes: {
                    type: 'base',
                    view: 'outlook:views/outlook/fields/labeled-array',
                    default: eventOptions,
                    options: eventOptions,
                    tooltip: true,
                    required: true,
                },
                calendarDefaultEntity: {
                    type: 'enum',
                    options: eventOptions,
                    default: "Meeting",
                    tooltip: true,
                    translation: 'Global.scopeNames',
                },
                calendarMainCalendar: {
                    type: 'base',
                    view: 'outlook:views/outlook/fields/main-calendar',
                    required: true,
                },
                calendarMonitoredCalendars: {
                    type: 'base',
                    view: 'outlook:views/outlook/fields/monitored-calendars',
                },
                removeOutlookCalendarEventIfRemovedInEspo: {
                    type: 'bool'
                },
                calendarCreateContacts: {
                    type: 'bool'
                },
                calendarSkipPrivate: {
                    type: 'bool'
                },
                calendarDontPushPastEvents: {
                    type: 'bool',
                    tooltip: true,
                },
            };
        },

        data: function () {
            return {
                integration: this.integration,
                helpText: this.helpText,
                isActive: this.model.get(this.productName+'Enabled') || false,
                isBlocked: this.isBlocked,
                fields: this.fieldList,
                hasFields: this.fieldList.length > 0,
                name: this.productName
            };
        },

        setup: function () {
            this.model = this.options.model;
            this.id = this.options.id;
            this.setupFields();
            this.model.defs.fields = $.extend(this.model.defs.fields, this.fields);
            this.model.populateDefaults();

            this.fieldList = [];

            for(i in this.fields) {
                this.createFieldView(this.fields[i].type, this.fields[i].view || null, i, false);
            }
        },

        createFieldView: function (type, view, name, readOnly, params) {
            var fieldView = view || this.getFieldManager().getViewName(type);
            this.createView(name, fieldView, {
                model: this.model,
                el: this.options.el + ' .field-' + name,
                defs: {
                    name: name,
                    params: params
                },
                mode: readOnly ? 'detail' : 'edit',
                readOnly: readOnly,
            });
            this.fieldList.push(name);
        },

        loadCalendars: function () {
            Espo.Ajax.postRequest('OutlookCalendar/action/usersCalendars', {
            }).then(function (calendars) {
                this.model.calendarList = calendars;
                this.checkCalendars();
                if (this.isBlocked) {
                    this.isBlocked = false;
                    this._parentView.reRender();
                }
            }.bind(this)).fail(function (xhr) {
                xhr.errorIsHandled = true;
                if (!this.isBlocked) {
                    this.isBlocked = true;
                    this.model.set(this.productName+'Enabled', false);
                    this.getParentView().reRender();
                }
            }.bind(this));
        },

        checkCalendars: function () {
            var mainCalendar = this.model.get('calendarMainCalendarId');

            if (!(mainCalendar in this.model.calendarList)) {
                this.model.set('calendarMainCalendarId','');
                this.model.set('calendarMainCalendarName','');
                this.getView('calendarMainCalendar').render();
            }

            var monitoredCalendars = this.model.get('calendarMonitoredCalendarsIds') || [];
            var monitoredCalendarsNames = this.model.get('calendarMonitoredCalendarsNames') || [];
            var render = false;

            for (key in monitoredCalendars) {
                if (!(monitoredCalendars[key] in this.model.calendarList)) {
                    delete monitoredCalendarsNames[monitoredCalendars[key]];
                    monitoredCalendars.splice(key, 1);
                    render = true;
                }
            }
            if (monitoredCalendars.length == 0) {
                render = true;
            }
            if (render) {
                this.model.set('calendarMonitoredCalendarsIds', monitoredCalendars);
                this.model.set('calendarMonitoredCalendarsNames',monitoredCalendarsNames);

                if (this.getView('calendarMonitoredCalendars')) {
                    this.getView('calendarMonitoredCalendars').render();
                }
            }
        },

        afterRender: function () {
            this.showCalendarFields();

            this.listenTo(this.model, 'change:calendarDirection', function () {
                this.showCalendarFields();
            }, this);

            this.enablingDefaultEntity();

            this.listenTo(this.model, 'change:calendarEntityTypes', function () {
                this.enablingDefaultEntity();
            }, this);

        },

        showCalendarFields: function() {
            var calendarDirection = this.model.get('calendarDirection');
            switch (calendarDirection) {
                case 'EspoToOutlook':
                    this.hideField('calendarMonitoredCalendars');
                    this.hideField('calendarDefaultEntity');
                    this.hideField('calendarCreateContacts');
                    this.hideField('removeOutlookCalendarEventIfRemovedInEspo');
                    this.hideField('calendarSkipPrivate');
                    this.showField('calendarDontPushPastEvents');
                    break;
                case 'OutlookToEspo':
                    this.showField('calendarMonitoredCalendars');
                    this.showField('calendarDefaultEntity');
                    this.showField('calendarCreateContacts');
                    this.showField('removeOutlookCalendarEventIfRemovedInEspo');
                    this.showField('calendarSkipPrivate');
                    this.hideField('calendarDontPushPastEvents');
                    break;
                case 'Both':
                    this.showField('calendarMonitoredCalendars');
                    this.showField('calendarDefaultEntity');
                    this.showField('calendarCreateContacts');
                    this.showField('removeOutlookCalendarEventIfRemovedInEspo');
                    this.showField('calendarSkipPrivate');
                    this.showField('calendarDontPushPastEvents');
                    break;
                default:
                    this.hideField('calendarMonitoredCalendars');
                    this.hideField('calendarDefaultEntity');
                    this.hideField('calendarCreateContacts');
                    this.hideField('removeOutlookCalendarEventIfRemovedInEspo');
                    this.hideField('calendarSkipPrivate');
                    this.hideField('calendarDontPushPastEvents');
            }
        },

        enablingDefaultEntity: function() {
            var calendarEntityTypes = this.model.get('calendarEntityTypes');
            var defaultEntityView = this.getView('calendarDefaultEntity');
            if (defaultEntityView && defaultEntityView.$el) {
                 defaultEntityView.$el.find('option').each(function (i, o) {
                    var $o = $(o);
                    if (calendarEntityTypes.indexOf($o.val()) == -1) {
                        $o.attr('disabled', 'disabled');
                        $o.removeAttr('selected');
                    } else {
                        $o.removeAttr('disabled');
                    }
                }.bind(this));
            }
        },

        setConnected: function () {
             this.loadCalendars();
        },

        setNotConnected: function () {

        },

        validate: function () {
            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                if (!view.readOnly && view.$el.is(':visible')) {
                    view.fetchToModel();
                }
            }, this);
            var notValid = false;
            if (this.model.get('enabled') && this.model.get(this.productName+'Enabled')) {
                this.fieldList.forEach(function (field) {
                    notValid = this.getView(field).validate() || notValid;
                }, this);
            }

            var defaultEntity = this.model.get('calendarDefaultEntity');
            var entities = this.model.get('calendarEntityTypes');
            var enititesView = this.getView('calendarEntityTypes');
            var defaultEntityView = this.getView('calendarDefaultEntity');
            if (defaultEntityView.$el.is(':visible')) {
                var defaultIsInList = false;
                var labelDuplicates = false;
                var labels = new Array();
                for (key in entities) {
                    var label = this.model.get(entities[key] + 'IdentificationLabel');
                    if ((label == null || label == '') && defaultEntity != entities[key]) {
                        var msg = this.translate('emptyNotDefaultEnitityLabel', 'messages','OutlookCalendar');
                        enititesView.showValidationMessage(msg, '[data-name="translatedValue"]:last');
                        notValid |= true;
                    } else {
                        if (labels.indexOf(label) >= 0) {
                            labelDuplicates = true;
                        }
                        labels.push(label);
                    }

                    if (entities[key] == defaultEntity) {
                        defaultIsInList = true;
                    }
                }

                if (!defaultIsInList) {
                    var msg = this.translate('defaultEntityIsRequiredInList', 'messages','OutlookCalendar');
                    defaultEntityView.showValidationMessage(msg);
                    notValid |= true;
                }

                if (labelDuplicates) {
                    var msg = this.translate('notUniqueIdentificationLabel', 'messages','OutlookCalendar');
                    enititesView.showValidationMessage(msg, '[data-name="translatedValue"]:last');
                    notValid |= true;
                }
            }
            return notValid;
        },

        hideField : function (field) {
             this.$el.find('.cell-' + field).addClass('hidden');
        },

        showField : function (field) {
             this.$el.find('.cell-' + field).removeClass('hidden');
        },
    });
});
