<?php
class Controller_Webhooks implements DevblocksHttpRequestHandler {
	
	function handleRequest(DevblocksHttpRequest $request) {
		$stack = $request->path;
		$db = DevblocksPlatform::getDatabaseService();
		
		array_shift($stack); // webhooks
		@$guid = array_shift($stack); // guid
		
		if(empty($guid) || false == ($webhook = DAO_WebhookHandler::getByGUID($guid)))
			return;
		
		// Load the webhook handler extension
		
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
		$defaults->class_name = 'View_WebhookHandler';
		$defaults->id = 'setup_webhook_handlers';
		
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