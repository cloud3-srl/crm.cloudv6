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

define('advanced:views/bpmn-flowchart-element/fields/message-replied-to', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        fetchEmptyValueAsNull: true,

        setupOptions: function () {
            var list = [''];
            this.translatedOptions = {
                '': this.translate('None'),
            };

            var flowchartCreatedEntitiesData = this.model.flowchartCreatedEntitiesData || {};

            for (var id in flowchartCreatedEntitiesData) {
                var item = flowchartCreatedEntitiesData[id];
                if (item.entityType !== 'Email') continue;

                this.translatedOptions[id] = item.text || id;
                list.push(id);
            }

            this.params.options = list;
        },
    });
});