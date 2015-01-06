<?php
/***********************************************************************
| Cerb(tm) developed by Webgroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2002-2014, Webgroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Devblocks Public License.
| The latest version of this license can be found here:
| http://cerberusweb.com/license
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerbweb.com	    http://www.webgroupmedia.com/
***********************************************************************/

abstract class AbstractEvent_Webhook extends Extension_DevblocksEvent {
	protected $_event_id = null; // override

	private function _getHttpBody() {
		$contents = "";
		
		$body_data = fopen( "php://input" , "rb" );
		while(!feof( $body_data ))
			$contents .= fread($body_data, 4096);
		fclose($body_data);
		
		return $contents;
	}
	
	/**
	 *
	 * @return Model_DevblocksEvent
	 */
	function generateSampleEventModel(Model_TriggerEvent $trigger) {
		return new Model_DevblocksEvent(
			$this->_event_id,
			array(
				'virtual_attendant_id' => $trigger->virtual_attendant_id,
			)
		);
	}
	
	function setEvent(Model_DevblocksEvent $event_model=null, Model_TriggerEvent $trigger=null) {
		$labels = array();
		$values = array();

		// HTTP client IP
		
		$labels['http_client_ip'] = 'HTTP client IP';
		$values['http_client_ip'] = $_SERVER['REMOTE_ADDR'];
		
		// HTTP verb
		
		$labels['http_verb'] = 'HTTP verb';
		$values['http_verb'] = strtoupper($_SERVER['REQUEST_METHOD']);
		
		// HTTP body
		
		$labels['http_body'] = 'HTTP body';
		$values['http_body'] = $this->_getHttpBody();
		
		// Populate HTTP headers
		
		$values['http_headers'] = array();
		
		foreach($_SERVER as $k => $v) {
			if('HTTP_' == substr($k, 0, 5)) {
				$values['http_headers'][strtolower(substr($k, 5))] = $v;
			}
		}
		
		// Populate HTTP params
		
		$values['http_params'] = array();
		
		foreach($_REQUEST as $k => $v) {
			$values['http_params'][strtolower(DevblocksPlatform::strAlphaNum(str_replace('-', '_', $k), '_', ''))] = $v;
		}
		
		// Load the VA of the current macro
		
		/**
		 * Virtual Attendant
		 */
		
		@$virtual_attendant_id = $event_model->params['virtual_attendant_id'];
		$merge_labels = array();
		$merge_values = array();
		CerberusContexts::getContext(CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT, $virtual_attendant_id, $merge_labels, $merge_values, null, true);

			// Merge
			CerberusContexts::merge(
				'va_',
				'',
				$merge_labels,
				$merge_values,
				$labels,
				$values
			);

		/**
		 * Return
		 */

		$this->setLabels($labels);
		$this->setValues($values);
	}
	
	function renderSimulatorTarget($trigger, $event_model) {
		$context = CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT;
		$context_id = $event_model->params['virtual_attendant_id'];
		DevblocksEventHelper::renderSimulatorTarget($context, $context_id, $trigger, $event_model);
	}
	
	function getValuesContexts($trigger) {
		$vals = array(
			'va_id' => array(
				'label' => 'Virtual Attendant',
				'context' => CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT,
			),
			'va_watchers' => array(
				'label' => 'Virtual Attendant Watchers',
				'context' => CerberusContexts::CONTEXT_WORKER,
				'is_multiple' => true,
			),
		);
		
		$vars = parent::getValuesContexts($trigger);
		
		$vals_to_ctx = array_merge($vals, $vars);
		DevblocksPlatform::sortObjects($vals_to_ctx, '[label]');
		
		return $vals_to_ctx;
	}
	
	function getConditionExtensions(Model_TriggerEvent $trigger) {
		$labels = $this->getLabels($trigger);
		$types = $this->getTypes();
		
		$labels['http_client_ip'] = 'HTTP client ip';
		$labels['http_header'] = 'HTTP header';
		$labels['http_param'] = 'HTTP parameter';
		$labels['http_verb'] = 'HTTP verb';
		
		$labels['va_link'] = 'Virtual attendant is linked';
		$labels['va_watcher_count'] = 'Virtual attendant watcher count';
		
		$types['http_client_ip'] = Model_CustomField::TYPE_SINGLE_LINE;
		$types['http_header'] = null;
		$types['http_param'] = null;
		$types['http_verb'] = Model_CustomField::TYPE_SINGLE_LINE;
		
		$types['va_link'] = null;
		$types['va_watcher_count'] = null;
		
		
		$conditions = $this->_importLabelsTypesAsConditions($labels, $types);

		return $conditions;
	}
	
	function renderConditionExtension($token, $as_token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','condition'.$seq);
		
		switch($as_token) {
			case 'http_header':
				$tpl->display('devblocks:cerb.webhooks::events/condition_http_header.tpl');
				break;
				
			case 'http_param':
				$tpl->display('devblocks:cerb.webhooks::events/condition_http_param.tpl');
				break;
			
			case 'va_link':
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::events/condition_link.tpl');
				break;
				
			case 'va_watcher_count':
				$tpl->display('devblocks:cerberusweb.core::internal/decisions/conditions/_number.tpl');
				break;
			
			default:
				break;
		}

		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('params');
	}
	
	function runConditionExtension($token, $as_token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		$pass = true;
		
		switch($as_token) {
			case 'http_header':
				@$name_key = strtolower($params['name']);
				@$http_headers = $dict->http_headers;
				@$value = $http_headers[$name_key];
				
				$pass = DevblocksPlatform::compareStrings($value, $params['value'], $params['oper']);
				break;
				
			case 'http_param':
				@$name_key = strtolower($params['name']);
				@$http_params = $dict->http_params;
				@$value = $http_params[$name_key];
				
				$pass = DevblocksPlatform::compareStrings($value, $params['value'], $params['oper']);
				break;
			
			case 'va_link':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');
				
				$from_context = null;
				$from_context_id = null;

				switch($as_token) {
					case 'email_link':
						$from_context = CerberusContexts::CONTEXT_VIRTUAL_ATTENDANT;
						@$from_context_id = $dict->va_id;
						break;
					default:
						$pass = false;
				}
				
				// Get links by context+id
				
				if(!empty($from_context) && !empty($from_context_id)) {
					@$context_strings = $params['context_objects'];
					$links = DAO_ContextLink::intersect($from_context, $from_context_id, $context_strings);
					
					// OPER: any, !any, all
					switch($oper) {
						case 'in':
							$pass = (is_array($links) && !empty($links));
							break;
						case 'all':
							$pass = (is_array($links) && count($links) == count($context_strings));
							break;
						default:
							$pass = false;
							break;
					}
					
				} else {
					$pass = false;
				}
				
				$pass = ($not) ? !$pass : $pass;
				break;
				
			case 'va_watcher_count':
				$not = (substr($params['oper'],0,1) == '!');
				$oper = ltrim($params['oper'],'!');

				switch($as_token) {
					default:
						$value = count($dict->va_watchers);
						break;
				}
				
				switch($oper) {
					case 'is':
						$pass = intval($value)==intval($params['value']);
						break;
					case 'gt':
						$pass = intval($value) > intval($params['value']);
						break;
					case 'lt':
						$pass = intval($value) < intval($params['value']);
						break;
				}
				
				$pass = ($not) ? !$pass : $pass;
				break;
				
			default:
				$pass = false;
				break;
		}
		
		return $pass;
	}
	
	function getActionExtensions(Model_TriggerEvent $trigger) {
		$actions =
			array(
				'add_watchers' => array('label' =>'Add watchers'),
				'create_comment' => array('label' =>'Create a comment'),
				'create_notification' => array('label' =>'Create a notification'),
				'create_task' => array('label' =>'Create a task'),
				'create_ticket' => array('label' =>'Create a ticket'),
				'send_email' => array('label' => 'Send email'),
				'set_links' => array('label' => 'Set links'),
				'set_http_header' => array('label' => 'Set HTTP response header'),
				'set_http_body' => array('label' => 'Set HTTP response body'),
				'set_timezone' => array('label' => 'Set timezone'),
			)
			+ DevblocksEventHelper::getActionCustomFieldsFromLabels($this->getLabels($trigger))
			;
			
		return $actions;
	}
	
	function renderActionExtension($token, $trigger, $params=array(), $seq=null) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('params', $params);

		if(!is_null($seq))
			$tpl->assign('namePrefix','action'.$seq);

		$labels = $this->getLabels($trigger);
		$tpl->assign('token_labels', $labels);
			
		switch($token) {
			case 'set_http_header':
				$tpl->display('devblocks:cerb.webhooks::events/action_set_http_header.tpl');
				break;
				
			case 'set_http_body':
				$tpl->display('devblocks:cerb.webhooks::events/action_set_http_body.tpl');
				break;
				
			case 'set_timezone':
				DevblocksEventHelper::renderActionSetVariableString($labels);
				break;
				
			case 'add_watchers':
				DevblocksEventHelper::renderActionAddWatchers($trigger);
				break;
			
			case 'create_comment':
				DevblocksEventHelper::renderActionCreateComment($trigger);
				break;
				
			case 'create_notification':
				DevblocksEventHelper::renderActionCreateNotification($trigger);
				break;
				
			case 'create_task':
				DevblocksEventHelper::renderActionCreateTask($trigger);
				break;
				
			case 'create_ticket':
				DevblocksEventHelper::renderActionCreateTicket($trigger);
				break;
				
			case 'send_email':
				DevblocksEventHelper::renderActionSendEmail($trigger);
				break;

			case 'set_links':
				DevblocksEventHelper::renderActionSetLinks($trigger);
				break;

			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token, $matches)) {
					$field_id = $matches[2];
					$custom_field = DAO_CustomField::get($field_id);
					DevblocksEventHelper::renderActionSetCustomField($custom_field, $trigger);
				}
				break;
		}
		
		$tpl->clearAssign('params');
		$tpl->clearAssign('namePrefix');
		$tpl->clearAssign('token_labels');
	}
	
	function simulateActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$virtual_attendant_id = $dict->va_id;

		if(empty($virtual_attendant_id))
			return;
		
		switch($token) {
			case 'set_http_header':
				$tpl_builder = DevblocksPlatform::getTemplateBuilder();

				@$name = $tpl_builder->build($params['name'], $dict);
				@$value = $tpl_builder->build($params['value'], $dict);

				if(!isset($dict->_http_response_headers) || !is_array($dict->_http_response_headers))
					$dict->_http_response_headers = array();
				
				$dict->_http_response_headers[$name] = $value;
				
				return sprintf(">>> Setting HTTP response header:\n%s: %s\n",
					$name,
					$value
				);
				break;
				
			case 'set_http_body':
				$tpl_builder = DevblocksPlatform::getTemplateBuilder();

				@$value = $tpl_builder->build($params['value'], $dict);

				$dict->_http_response_body = $value;
				
				return sprintf(">>> Setting HTTP response body:\n%s\n",
					$value
				);
				break;
			
			case 'set_timezone':
				$tpl_builder = DevblocksPlatform::getTemplateBuilder();

				@$value = $tpl_builder->build($params['value'], $dict);

				@date_default_timezone_set($value);
				
				return sprintf(">>> Setting timezone: %s\nCurrent time is %s\n",
					$value,
					date(DevblocksPlatform::getDateTimeFormat())
				);
				break;
			
			case 'add_watchers':
				return DevblocksEventHelper::simulateActionAddWatchers($params, $dict, 'va_id');
				break;
				
			case 'create_comment':
				return DevblocksEventHelper::simulateActionCreateComment($params, $dict, 'va_id');
				break;
				
			case 'create_notification':
				return DevblocksEventHelper::simulateActionCreateNotification($params, $dict, 'va_id');
				break;
				
			case 'create_task':
				return DevblocksEventHelper::simulateActionCreateTask($params, $dict, 'va_id');
				break;
				
			case 'create_ticket':
				return DevblocksEventHelper::simulateActionCreateTicket($params, $dict);
				break;
				
			case 'send_email':
				return DevblocksEventHelper::simulateActionSendEmail($params, $dict);
				break;
				
			case 'set_links':
				return DevblocksEventHelper::simulateActionSetLinks($trigger, $params, $dict);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token))
					return DevblocksEventHelper::simulateActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
	function runActionExtension($token, $trigger, $params, DevblocksDictionaryDelegate $dict) {
		@$virtual_attendant_id = $dict->va_id;

		if(empty($virtual_attendant_id))
			return;
		
		switch($token) {
			case 'set_http_header':
				$tpl_builder = DevblocksPlatform::getTemplateBuilder();

				@$name = $tpl_builder->build($params['name'], $dict);
				@$value = $tpl_builder->build($params['value'], $dict);

				if(!isset($dict->_http_response_headers) || !is_array($dict->_http_response_headers))
					$dict->_http_response_headers = array();
				
				$dict->_http_response_headers[$name] = $value;
				break;
				
			case 'set_http_body':
				$tpl_builder = DevblocksPlatform::getTemplateBuilder();

				@$value = $tpl_builder->build($params['value'], $dict);
				
				if(false !== $value)
					$dict->_http_response_body = $value;
				break;
				
			case 'set_timezone':
				$tpl_builder = DevblocksPlatform::getTemplateBuilder();

				@$value = $tpl_builder->build($params['value'], $dict);

				@date_default_timezone_set($value);
				break;
			
			case 'add_watchers':
				DevblocksEventHelper::runActionAddWatchers($params, $dict, 'va_id');
				break;
			
			case 'create_comment':
				DevblocksEventHelper::runActionCreateComment($params, $dict, 'va_id');
				break;
				
			case 'create_notification':
				DevblocksEventHelper::runActionCreateNotification($params, $dict, 'va_id');
				break;
				
			case 'create_task':
				DevblocksEventHelper::runActionCreateTask($params, $dict, 'va_id');
				break;

			case 'create_ticket':
				DevblocksEventHelper::runActionCreateTicket($params, $dict);
				break;
				
			case 'send_email':
				DevblocksEventHelper::runActionSendEmail($params, $dict);
				break;
				
			case 'set_links':
				DevblocksEventHelper::runActionSetLinks($trigger, $params, $dict);
				break;
				
			default:
				if(preg_match('#set_cf_(.*?)_custom_([0-9]+)#', $token))
					return DevblocksEventHelper::runActionSetCustomField($token, $params, $dict);
				break;
		}
	}
	
};