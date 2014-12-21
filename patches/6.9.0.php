<?php
$db = DevblocksPlatform::getDatabaseService();
$logger = DevblocksPlatform::getConsoleLog();
$tables = $db->metaTables();

// ===========================================================================
// webhook_handler

if(!isset($tables['webhook_handler'])) {
	$sql = sprintf("
		CREATE TABLE IF NOT EXISTS webhook_handler (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) DEFAULT '',
			guid VARCHAR(40) DEFAULT '',
			updated_at INT UNSIGNED NOT NULL DEFAULT 0,
			extension_id VARCHAR(255) DEFAULT '',
			extension_params_json TEXT NOT NULL,
			PRIMARY KEY (id),
			INDEX guid (guid(3))
		) ENGINE=%s;
	", APP_DB_ENGINE);
	$db->Execute($sql);

	$tables['webhook_handler'] = 'webhook_handler';
}

return TRUE;