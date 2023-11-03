{{#if isEmpty}}
    {{#ifNotEqual mode 'edit'}}
        <div class="form-group">{{translate 'None'}}</div>
    {{/ifNotEqual}}
{{/if}}

<div class="item-list-container list no-side-margin">{{{itemList}}}</div>