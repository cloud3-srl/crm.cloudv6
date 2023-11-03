<div class="input-group">
    <input type="text" class="main-element form-control" data-name="{{name}}" {{#if isProduct}} readonly="true" {{/if}}value="{{value}}" {{#if params.maxLength}} maxlength="{{params.maxLength}}"{{/if}} autocomplete="espo-{{name}}">
    <span class="input-group-btn">
        <button class="btn btn-default{{#if productSelectDisabled}} disabled{{/if}} btn-icon" data-action="selectProduct" title="{{translate 'Select Product' scope='Opportunity'}}">
            <span class="fas fa-angle-up"></span>
        </button>
    </span>
</div>