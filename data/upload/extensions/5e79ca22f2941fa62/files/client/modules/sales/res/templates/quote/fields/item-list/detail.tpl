{{#if isEmpty}}
    {{#ifNotEqual mode 'edit'}}
        <div class="form-group">{{translate 'None'}}</div>
    {{/ifNotEqual}}
{{/if}}

<div class="item-list-container list no-side-margin">{{{itemList}}}</div>

<div class="row{{#unless showFields}} hidden{{/unless}} totals-row">
    <hr class="no-side-margin" style="margin-top: 0;">

    <div class="cell col-sm-6 col-xs-6 form-group">
    </div>

    {{#each totalLayout}}
    <div class="cell{{#unless isFirst}} col-sm-offset-6 col-xs-offset-6{{/unless}} col-sm-6 col-xs-6 form-group">
        <label class="field control-label">
            {{translate name category='fields' scope=../scope}}
        </label>
        <div class="field" data-name="total-{{name}}">
            {{{var key ../this}}}
        </div>
    </div>
    {{/each}}
</div>
