<div class="item-list-container list no-side-margin">{{{itemList}}}</div>
<div class="">
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
<div class="row{{#unless showCurrency}} hidden{{/unless}} margin-top">
    <div class="cell col-md-2 col-sm-3 col-xs-6">
        <label class="control-label">
            {{translate 'currency' category='fields' scope='Quote'}}
        </label>
        <div class="field" data-name="total-currency">{{{currencyField}}}</div>
    </div>
</div>