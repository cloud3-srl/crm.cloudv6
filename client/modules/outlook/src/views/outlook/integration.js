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

define('outlook:views/outlook/integration', ['views/admin/integrations/oauth2', 'model'], function (Dep, Model) {

    return Dep.extend({

        products: [],

        setup: function () {
            this.integration = this.options.integration;

            this.redirectUri = this.getConfig().get('siteUrl').replace(/\/$/, '') + '/oauth-callback.php';

            this.helpText = false;
            if (this.getLanguage().has(this.integration, 'help', 'Integration')) {
                this.helpText = this.translate(this.integration, 'help', 'Integration');

                if (this.getHelper().transformMarkdownText) {
                    this.helpText = this.getHelper().transformMarkdownText(this.helpText, {});
                }
                else if (this.getHelper().transfromMarkdownText) {
                    this.helpText = this.getHelper().transfromMarkdownText(this.helpText, {});
                }
            }

            this.fieldList = [];
            this.fields = [];

            this.dataFieldList = [];

            this.model = new Model();
            this.model.id = this.integration;
            this.model.name = 'Integration';
            this.model.urlRoot = 'Integration';

            this.model.defs = {
                fields: {
                    enabled: {
                        required: true,
                        type: 'bool'
                    },
                }
            };

            this.wait(true);

            this.fields = this.getMetadata().get(['integrations', this.integration, 'fields']);

            Object.keys(this.fields).forEach(function (name) {
                this.model.defs.fields[name] = this.fields[name];
                this.dataFieldList.push(name);
            }, this);
            this.products = this.getMetadata().get(['integrations', this.integration, 'products']);
            this.model.populateDefaults();

            this.listenToOnce(this.model, 'sync', function () {
                this.createFieldView('bool', 'enabled');
                Object.keys(this.fields).forEach(function (name) {
                    this.createFieldView(this.fields[name]['type'], name, null, this.fields[name]);
                }, this);

                this.wait(false);
            }, this);

            this.model.fetch();
        }
    });
});
