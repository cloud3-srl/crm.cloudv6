

<div class="item-list-container list no-side-margin">{{{itemList}}}</div>
<div class="">
    <button class="btn btn-default btn-icon" data-action="addItem" title="{{translate 'Add Item' scope='Opportunity'}}"><span class="fas fa-plus"></span></button>
</div>
<div class="row{{#unless showCurrency}} hidden{{/unless}} margin-top">
    <div class="cell col-md-2 col-sm-3 col-xs-6">
        <label class="control-label">
            {{translate 'currency' category='fields' scope='Quote'}}
        </label>
        <div class="field" data-name="total-currency">{{{currencyField}}}</div>
    </div>
</div>