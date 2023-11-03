
<div class="item-list-container list no-side-margin">{{{itemList}}}</div>
<div class="button-container">
    <button class="btn btn-default btn-icon" data-action="addItem" title="{{translate 'Add Item' scope='Opportunity'}}"><span class="fas fa-plus"></span></button>
</div>
<div class="row{{#unless showFields}} hidden{{/unless}} totals-row">
    <hr class="no-side-margin" style="margin-top: 0;">

    <div class="cell col-sm-6 col-xs-6 form-group">
        <label class="control-label">
            {{translate 'currency' category='fields' scope=scope}}
        </label>
        <div class="field" data-name="total-currency">{{{currencyField}}}</div>
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