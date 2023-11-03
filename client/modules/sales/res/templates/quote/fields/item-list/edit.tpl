<div class="item-list-container list no-side-margin">{{{itemList}}}</div>
<div class="button-container">
    <div class="btn-group">
        <button
            class="btn btn-default btn-icon radius-right"
            data-action="addItem"
            title="{{translate 'Add Item' scope='Opportunity'}}"
        ><span class="fas fa-plus"></span></button>
        {{#if showAddProducts}}
        <button
            type="button"
            class="btn btn-text btn-icon dropdown-toggle"
            data-toggle="dropdown"
        ><span class="fas fa-ellipsis-h"></span></button>
        <ul class="dropdown-menu">
            <li>
                <a
                    role="button"
                    data-action="addProducts"
                    class="action"
                >{{translate 'Add Products' scope='Opportunity'}}</a>
            </li>
        </ul>
        {{/if}}
    </div>
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
