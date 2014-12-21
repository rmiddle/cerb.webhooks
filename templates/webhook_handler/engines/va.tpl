{$uniq_id = uniqid()}

<div style="padding:5px 10px;">
	A Virtual Attendant behavior will handle all HTTP requests to this webhook. 
</div>

{if $model->extension_params.behavior_id}
{$model_behavior = DAO_TriggerEvent::get($model->extension_params.behavior_id)} 
{/if}

<div style="padding:5px 10px;" id="{$uniq_id}">
	<b>Behavior:</b>
	<p style="margin-left:5px;">
		<select class="cerb-select-va">
			<option value=""></option>
			{foreach from=$virtual_attendants item=va}
			<option value="{$va->id}" {if $model_behavior->virtual_attendant_id==$va->id}selected="selected"{/if}>{$va->name}</option>
			{/foreach}
		</select>
		
		<select style="display:none;" class="cerb-select-behavior-options">
			{foreach from=$behaviors item=behavior}
			<option value="{$behavior->id}" va_id="{$behavior->virtual_attendant_id}">{$behavior->title}</option>
			{/foreach}
		</select>
		
		<select name="extension_params[{$engine->id}][behavior_id]" class="cerb-select-behavior">
			{foreach from=$behaviors item=behavior}
				{if $model_behavior->virtual_attendant_id == $behavior->virtual_attendant_id}
				<option value="{$behavior->id}" va_id="{$behavior->virtual_attendant_id}" {if $model_behavior->id==$behavior_id}selected="selected"{/if}>{$behavior->title}</option>
				{/if}
			{/foreach}
		</select>
	</p>
</div>

<script type="text/javascript">
$(function() {
	var $div = $('#{$uniq_id}');
	var $select_behavior_options = $div.find('select.cerb-select-behavior-options');
	var $select_va = $div.find('select.cerb-select-va');
	var $select_behavior = $div.find('select.cerb-select-behavior');
	
	$select_va.on('change', function() {
		$select_behavior.find('option').remove();
		
		var $options = $select_behavior_options.find('option[va_id=' + $(this).val() + ']');
		
		$options.each(function() {
			$select_behavior.append($(this).clone());
		});
	});
});
</script>
