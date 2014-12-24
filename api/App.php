<?php
abstract class Extension_WebhookListenerEngine extends DevblocksExtension {
	const POINT = 'cerb.webhooks.listener.engine';
	
	protected $_config = null;
	
	public static function getAll($as_instances=false) {
		$engines = DevblocksPlatform::getExtensions(self::POINT, $as_instances);
		if($as_instances)
			DevblocksPlatform::sortObjects($engines, 'manifest->name');
		else
			DevblocksPlatform::sortObjects($engines, 'name');
		return $engines;
	}
	
	/**
	 * @param string $id
	 * @return Extension_WebhookListenerEngine
	 */
	public static function get($id) {
		static $extensions = null;
		
		if(isset($extensions[$id]))
			return $extensions[$id];
		
		if(!isset($extensions[$id])) {
			if(null == ($ext = DevblocksPlatform::getExtension($id, true)))
				return;
			
			if(!($ext instanceof Extension_WebhookListenerEngine))
				return;
			
			$extensions[$id] = $ext;
			return $ext;
		}
	}
	
	function getConfig() {
		if(is_null($this->_config)) {
			$this->_config = $this->getParams();
		}
		
		return $this->_config;
	}
	
	abstract function renderConfig(Model_WebhookListener $model);
	abstract function handleWebhookRequest(Model_WebhookListener $webhook);
};

class WebhookListenerEngine_VirtualAttendantBehavior extends Extension_WebhookListenerEngine {
	const ID = 'cerb.webhooks.listener.engine.va';
	
	function renderConfig(Model_WebhookListener $model) {
		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('engine', $this);
		$tpl->assign('params', $model->extension_id == $this->manifest->id ? $model->extension_params : array());
		
		$behaviors = DAO_TriggerEvent::getReadableByActor($active_worker, 'event.webhook.received', false);
		$virtual_attendants = DAO_VirtualAttendant::getReadableByActor($active_worker);

		// Filter virtual attendants to those with existing behaviors
		
		$visible_va_ids = array();

		if(is_array($behaviors));
		foreach($behaviors as $behavior_id => $behavior) {
			$visible_va_ids[$behavior->virtual_attendant_id] = true;
		}
		
		$virtual_attendants = array_filter($virtual_attendants, function($va) use ($visible_va_ids) {
			if(isset($visible_va_ids[$va->id]))
				return true;
			
			return false;
		});
		
		$tpl->assign('behaviors', $behaviors);
		$tpl->assign('virtual_attendants', $virtual_attendants);
		
		$tpl->display('devblocks:cerb.webhooks::webhook_listener/engines/va.tpl');
	}
	
	function handleWebhookRequest(Model_WebhookListener $webhook) {
		if(false == ($behavior_id = @$webhook->extension_params['behavior_id']))
			return false;

		if(false == ($behavior = DAO_TriggerEvent::get($behavior_id)))
			return false;
		
		$variables = array();
		
		$dicts = Event_WebhookReceived::trigger($behavior->id, $variables);
		$dict = $dicts[$behavior->id];

		if(!($dict instanceof DevblocksDictionaryDelegate))
			return;
		
		// HTTP response headers
		
		if(isset($dict->_http_response_headers) && is_array($dict->_http_response_headers)) {
			foreach($dict->_http_response_headers as $header_k => $header_v) {
				header(sprintf("%s: %s",
					$header_k,
					$header_v
				));
			}
		}
		
		// HTTP response body
		
		if(isset($dict->_http_response_body)) {
			echo $dict->_http_response_body;
		}
	}
};

class Controller_Webhooks implements DevblocksHttpRequestHandler {
	
	function handleRequest(DevblocksHttpRequest $request) {
		$stack = $request->path;
		$db = DevblocksPlatform::getDatabaseService();
		
		array_shift($stack); // webhooks
		@$guid = array_shift($stack); // guid
		
		if(empty($guid) || false == ($webhook = DAO_WebhookListener::getByGUID($guid)))
			return;
		
		// Load the webhook listener extension
		
		if(false == ($webhook_ext = $webhook->getExtension()))
			return;
		
		$webhook_ext->handleWebhookRequest($webhook);
	}
	
	function writeResponse(DevblocksHttpResponse $response) {
	}
};

class Webhooks_SetupPageSection extends Extension_PageSection {
	const ID = 'webhooks.setup.section';
	
	function render() {
		$settings = DevblocksPlatform::getPluginSettingsService();
		
		$tpl = DevblocksPlatform::getTemplateService();
	
		$defaults = new C4_AbstractViewModel();
		$defaults->class_name = 'View_WebhookListener';
		$defaults->id = 'setup_webhook_listeners';
		
		$view = C4_AbstractViewLoader::getView($defaults->id, $defaults);
		$tpl->assign('view', $view);
		
		$tpl->display('devblocks:cerb.webhooks::setup/page.tpl');
	}
	
};

class Webhooks_SetupPluginsMenuItem extends Extension_PageMenuItem {
	const ID = 'webhooks.setup.menu.plugins';
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->display('devblocks:cerb.webhooks::setup/menu_item.tpl');
	}
};