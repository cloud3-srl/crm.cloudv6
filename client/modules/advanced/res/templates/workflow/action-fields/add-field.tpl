<div class="btn-group">
    <button class="btn btn-default radius-right" type="button" data-toggle="dropdown">{{translate 'Add Field' scope='Workflow'}} <span class="caret"></span></button>
    <ul class="dropdown-menu">
    {{#each fieldList}}
        <li><a role="button" tabindex="0" data-action="addField" data-field="{{this}}">{{translate this scope=../scope category="fields"}}</a></li>
    {{/each}}
    </ul>
</div>
