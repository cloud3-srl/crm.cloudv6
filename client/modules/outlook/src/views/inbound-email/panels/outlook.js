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

define('outlook:views/inbound-email/panels/outlook', 'view', function (Dep) {

    return Dep.extend({

        template: 'outlook:inbound-email/panels/outlook',

        data: function () {
            var data = {};

            return data;
        },

        events: {
            'click [data-action="connect"]': 'actionConnect',
            'click [data-action="disconnect"]': 'actionDisconnect',
        },

        setup: function () {
            this.isLoaded = false;

            this.id = this.model.id;

            Espo.Ajax.postRequest('OutlookMail/action/ping', {
                id: this.id,
                entityType: this.model.entityType,
            }).then(
                function (response) {
                    this.clientId = response.clientId;
                    this.redirectUri = response.redirectUri;
                    if (response.isConnected) {
                        this.setConnected();
                    } else {
                        this.setNotConnected();
                    }
                }.bind(this)
            );
        },

        setConnected: function () {
            this.isLoaded = true;
            this.isConnected = true;

            this.reRender();
        },

        setNotConnected: function () {
            this.isLoaded = true;
            this.isConnected = false;

            this.reRender();
        },

        actionConnect: function () {
            var endpoint = this.getMetadata().get(['integrations', 'Outlook', 'params', 'endpoint']);
            var tenant = this.getHelper().getAppParam('outlookTenant') || 'common';
            endpoint = endpoint.replace('{tenant}', tenant);

            this.popup({
                path: endpoint,
                params: {
                    client_id: this.clientId,
                    redirect_uri: this.redirectUri,
                    scope: this.getMetadata().get(['integrations', 'Outlook', 'params', 'scopeMail']),
                    response_type: 'code',
                    access_type: 'offline',
                }
            }, function (res) {
                if (res.error) {
                    console.error(res);
                    Espo.Ui.error('Error response, more details in console');
                    return;
                }
                if (res.code) {
                    this.$el.find('[data-action="connect"]').addClass('disabled');

                    Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                    Espo.Ajax.postRequest('OutlookMail/action/connect', {
                        id: this.id,
                        code: res.code,
                        entityType: this.model.entityType,
                    }).then(
                        function (response) {
                            this.notify(false);
                            if (response === true) {
                                this.setConnected();
                            } else {
                                this.setNotConnected();
                            }
                            this.$el.find('[data-action="connect"]').removeClass('disabled');
                        }.bind(this)
                    ).fail(
                        function () {
                            this.$el.find('[data-action="connect"]').removeClass('disabled');
                        }.bind(this)
                    );

                } else {
                    Espo.Ui.error('Error occured, bad response');
                }
            });
        },

        actionDisconnect: function () {
            this.confirm(this.translate('disconnectConfirmation', 'messages', 'ExternalAccount'), function () {
                this.$el.find('[data-action="disconnect"]').addClass('disabled');
                this.$el.find('[data-action="connect"]').addClass('disabled');

                Espo.Ajax.postRequest('OutlookMail/action/disconnect', {
                    id: this.id,
                    entityType: this.model.entityType,
                }).then(
                    function () {
                        this.setNotConnected();

                        this.$el.find('[data-action="disconnect"]').removeClass('disabled');
                        this.$el.find('[data-action="connect"]').removeClass('disabled');
                    }.bind(this)
                ).fail(
                    function () {
                        this.$el.find('[data-action="disconnect"]').removeClass('disabled');
                        this.$el.find('[data-action="connect"]').removeClass('disabled');
                    }.bind(this)
                );
            }.bind(this));
        },

        popup: function (options, callback) {
            options.windowName = options.windowName || 'ConnectWithOAuth';
            options.windowOptions = options.windowOptions || 'location=0,status=0,width=800,height=600';
            options.callback = options.callback || function(){ window.location.reload(); };

            var self = this;

            var path = options.path;

            var arr = [];
            var params = (options.params || {});
            for (var name in params) {
                if (params[name]) {
                    arr.push(name + '=' + encodeURI(params[name]));
                }
            }
            path += '?' + arr.join('&');

            var parseUrl = function (str) {
                var data = {};

                str = str.substr(str.indexOf('?') + 1, str.length);
                str.split('&').forEach(function (part) {
                    var arr = part.split('=');
                    var name = decodeURI(arr[0]);
                    var value = decodeURI(arr[1] || '');
                    data[name] = value;
                }, this);

                if (!data.error && !data.code) {
                    return null;
                }

                return data;
            }

            var popup = window.open(path, options.windowName, options.windowOptions);
            var interval = window.setInterval(function () {
                if (popup.closed) {
                    window.clearInterval(interval);
                } else {
                    var res = parseUrl(popup.location.href.toString());
                    if (res) {
                        callback.call(self, res);
                        popup.close();
                        window.clearInterval(interval);
                    }
                }
            }, 500);
        },

    });
});
