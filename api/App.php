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
