
{{#each listLayout}}
    <td
        {{#if width}}
            width="{{width}}%"
        {{else}}
            {{#if widthPx}} width="{{widthPx}}"{{/if}}
        {{/if}}
        {{#if align}} style="text-align: {{align}}"{{/if}}
    >
        {{#ifEqual name "name"}}
        <div class="field" data-name="item-name">
            {{{../../nameField}}}
        </div>
        <div class="field small" data-name="item-description">
            {{{../../descriptionField}}}
        </div>
        {{else}}
        <div class="field{{#ifEqual align 'right'}} pull-right{{/ifEqual}}{{#ifEqual ../../mode 'edit'}}{{#if isReadOnly}} detail-field-container{{/if}}{{/ifEqual}}" data-name="item-{{name}}">
            {{{var key ../../this}}}
        </div>
        {{/ifEqual}}
    </td>
{{/each}}

{{#ifEqual mode 'edit'}}
<td width="{{#ifEqual mode 'edit'}}51{{else}}1{{/ifEqual}}">
    <div class="{{#ifEqual mode 'edit'}} detail-field-container{{/ifEqual}}">
        {{#ifEqual mode 'edit'}}
        <a href="javascript:" class="pull-right" data-action="removeItem" data-id="{{id}}" title="{{translate 'Remove'}}"><span class="fas fa-times"></span></a>
        <span class="fas fa-magnet drag-icon text-muted" style="cursor: pointer;"></span>
        {{/ifEqual}}
    </div>
</td>
{{/ifEqual}}
{{#if showRowActions}}
<td class="cell" data-name="buttons">
    {{{rowActions}}}
</td>
{{/if}}
