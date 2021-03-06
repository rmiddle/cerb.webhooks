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

class Event_WebhookReceived extends AbstractEvent_Webhook {
	const ID = 'event.webhook.received';
	
	function __construct($manifest) {
		parent::__construct($manifest);
		$this->_event_id = self::ID;
	}
	
	static function trigger($trigger_id, $variables=array()) {

		// [TODO] Abstract the HTTP headers/params? 
		
		if(false == ($behavior = DAO_TriggerEvent::get($trigger_id)))
			return;
		
		// Look up the trigger's owning Virtual Attendant
		if(false == ($va = $behavior->getVirtualAttendant()))
			return;
		
		$events = DevblocksPlatform::getEventService();
		return $events->trigger(
			new Model_DevblocksEvent(
				self::ID,
				array(
					'virtual_attendant_id' => $va->id,
					'_variables' => $variables,
					'_whisper' => array(
						'_trigger_id' => array($trigger_id),
					),
				)
			)
		);
	}
};