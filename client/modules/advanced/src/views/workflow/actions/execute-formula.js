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

define('advanced:views/workflow/actions/execute-formula',
    ['advanced:views/workflow/actions/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        type: 'executeFormula',

        template: 'advanced:workflow/actions/execute-formula',

        defaultActionData: {
            formula: null,
        },

        afterRender: function () {
            this.$formulaField = this.$el.find('.field[data-name="formula"]');
            this.renderFormula();
        },

    });
});
