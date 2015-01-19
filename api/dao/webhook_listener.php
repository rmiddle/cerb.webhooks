<?php
class DAO_WebhookListener extends Cerb_ORMHelper {
	const ID = 'id';
	const NAME = 'name';
	const GUID = 'guid';
	const UPDATED_AT = 'updated_at';
	const EXTENSION_ID = 'extension_id';
	const EXTENSION_PARAMS_JSON = 'extension_params_json';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "INSERT INTO webhook_listener () VALUES ()";
		$db->Execute($sql);
		$id = $db->LastInsertId();
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields, $check_deltas=true) {
		if(!is_array($ids))
			$ids = array($ids);
		
		// Make a diff for the requested objects in batches
		
		$chunks = array_chunk($ids, 100, true);
		while($batch_ids = array_shift($chunks)) {
			if(empty($batch_ids))
				continue;
				
			// Send events
			if($check_deltas) {
				CerberusContexts::checkpointChanges('cerberusweb.contexts.webhook_listener', $batch_ids);
			}
			
			// Make changes
			parent::_update($batch_ids, 'webhook_listener', $fields);
			
			// Send events
			if($check_deltas) {
				// Trigger an event about the changes
				$eventMgr = DevblocksPlatform::getEventService();
				$eventMgr->trigger(
					new Model_DevblocksEvent(
						'dao.webhook_listener.update',
						array(
							'fields' => $fields,
						)
					)
				);
				
				// Log the context update
				DevblocksPlatform::markContextChanged('cerberusweb.contexts.webhook_listener', $batch_ids);
			}
		}
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('webhook_listener', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @param mixed $sortBy
	 * @param mixed $sortAsc
	 * @param integer $limit
	 * @return Model_WebhookListener[]
	 */
	static function getWhere($where=null, $sortBy=null, $sortAsc=true, $limit=null) {
		$db = DevblocksPlatform::getDatabaseService();

		list($where_sql, $sort_sql, $limit_sql) = self::_getWhereSQL($where, $sortBy, $sortAsc, $limit);
		
		// SQL
		$sql = "SELECT id, name, guid, updated_at, extension_id, extension_params_json ".
			"FROM webhook_listener ".
			$where_sql.
			$sort_sql.
			$limit_sql
		;
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_WebhookListener
	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * 
	 * @param string $guid
	 * @return Model_WebhookListener
	 */
	static function getByGUID($guid) {
		$results = self::getWhere(sprintf("guid = %s",
			Cerb_ORMHelper::qstr($guid)
		));
		
		if(empty($results) || !is_array($results))
			return false;
		
		return array_shift($results);
	}
	
	/**
	 * @param resource $rs
	 * @return Model_WebhookListener[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object = new Model_WebhookListener();
			$object->id = $row['id'];
			$object->name = $row['name'];
			$object->guid = $row['guid'];
			$object->updated_at = $row['updated_at'];
			$object->extension_id = $row['extension_id'];
			
			$extension_params_json = $row['extension_params_json'];
			
			// Deserialize extension params
			if(!empty($extension_params_json) && false != ($extension_params = json_decode($extension_params_json, true)))
				$object->extension_params = $extension_params;
			
			$objects[$object->id] = $object;
		}
		
		mysqli_free_result($rs);
		
		return $objects;
	}
	
	static function random() {
		return self::_getRandom('webhook_listener');
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->Execute(sprintf("DELETE FROM webhook_listener WHERE id IN (%s)", $ids_list));
		
		// Fire event
		$eventMgr = DevblocksPlatform::getEventService();
		$eventMgr->trigger(
			new Model_DevblocksEvent(
				'context.delete',
				array(
					'context' => 'cerberusweb.contexts.webhook_listener',
					'context_ids' => $ids
				)
			)
		);
		
		return true;
	}
	
	public static function getSearchQueryComponents($columns, $params, $sortBy=null, $sortAsc=null) {
		$fields = SearchFields_WebhookListener::getFields();
		
		// Sanitize
		if('*'==substr($sortBy,0,1) || !isset($fields[$sortBy]))
			$sortBy=null;

		list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		
		$select_sql = sprintf("SELECT ".
			"webhook_listener.id as %s, ".
			"webhook_listener.name as %s, ".
			"webhook_listener.guid as %s, ".
			"webhook_listener.updated_at as %s, ".
			"webhook_listener.extension_id as %s, ".
			"webhook_listener.extension_params_json as %s ",
				SearchFields_WebhookListener::ID,
				SearchFields_WebhookListener::NAME,
				SearchFields_WebhookListener::GUID,
				SearchFields_WebhookListener::UPDATED_AT,
				SearchFields_WebhookListener::EXTENSION_ID,
				SearchFields_WebhookListener::EXTENSION_PARAMS_JSON
			);
			
		$join_sql = "FROM webhook_listener ".
			(isset($tables['context_link']) ? sprintf("INNER JOIN context_link ON (context_link.to_context = %s AND context_link.to_context_id = webhook_listener.id) ", Cerb_ORMHelper::qstr('cerberusweb.contexts.webhook_listener')) : " ").
			'';
		
		// Custom field joins
		list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
			$tables,
			$params,
			'webhook_listener.id',
			$select_sql,
			$join_sql
		);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "WHERE 1 ");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
	
		// Virtuals
		
		$args = array(
			'join_sql' => &$join_sql,
			'where_sql' => &$where_sql,
			'tables' => &$tables,
			'has_multiple_values' => &$has_multiple_values
		);
	
		array_walk_recursive(
			$params,
			array('DAO_WebhookListener', '_translateVirtualParameters'),
			$args
		);
		
		return array(
			'primary_table' => 'webhook_listener',
			'select' => $select_sql,
			'join' => $join_sql,
			'where' => $where_sql,
			'has_multiple_values' => $has_multiple_values,
			'sort' => $sort_sql,
		);
	}
	
	private static function _translateVirtualParameters($param, $key, &$args) {
		if(!is_a($param, 'DevblocksSearchCriteria'))
			return;
			
		$from_context = 'cerberusweb.contexts.webhook_listener';
		$from_index = 'webhook_listener.id';
		
		$param_key = $param->field;
		settype($param_key, 'string');
		
		switch($param_key) {
			case SearchFields_WebhookListener::VIRTUAL_CONTEXT_LINK:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualContextLinks($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_WebhookListener::VIRTUAL_HAS_FIELDSET:
				self::_searchComponentsVirtualHasFieldset($param, $from_context, $from_index, $args['join_sql'], $args['where_sql']);
				break;
		
			case SearchFields_WebhookListener::VIRTUAL_WATCHERS:
				$args['has_multiple_values'] = true;
				self::_searchComponentsVirtualWatchers($param, $from_context, $from_index, $args['join_sql'], $args['where_sql'], $args['tables']);
				break;
		}
	}
	
	/**
	 * Enter description here...
	 *
	 * @param array $columns
	 * @param DevblocksSearchCriteria[] $params
	 * @param integer $limit
	 * @param integer $page
	 * @param string $sortBy
	 * @param boolean $sortAsc
	 * @param boolean $withCounts
	 * @return array
	 */
	static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		
		// Build search queries
		$query_parts = self::getSearchQueryComponents($columns,$params,$sortBy,$sortAsc);

		$select_sql = $query_parts['select'];
		$join_sql = $query_parts['join'];
		$where_sql = $query_parts['where'];
		$has_multiple_values = $query_parts['has_multiple_values'];
		$sort_sql = $query_parts['sort'];
		
		$sql =
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY webhook_listener.id ' : '').
			$sort_sql;
			
		if($limit > 0) {
			$rs = $db->SelectLimit($sql,$limit,$page*$limit) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
			$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
			$total = mysqli_num_rows($rs);
		}
		
		$results = array();
		
		while($row = mysqli_fetch_assoc($rs)) {
			$object_id = intval($row[SearchFields_WebhookListener::ID]);
			$results[$object_id] = $row;
		}

		$total = count($results);
		
		if($withCounts) {
			// We can skip counting if we have a less-than-full single page
			if(!(0 == $page && $total < $limit)) {
				$count_sql =
					($has_multiple_values ? "SELECT COUNT(DISTINCT webhook_listener.id) " : "SELECT COUNT(webhook_listener.id) ").
					$join_sql.
					$where_sql;
				$total = $db->GetOne($count_sql);
			}
		}
		
		mysqli_free_result($rs);
		
		return array($results,$total);
	}

};

class SearchFields_WebhookListener implements IDevblocksSearchFields {
	const ID = 'w_id';
	const NAME = 'w_name';
	const GUID = 'w_guid';
	const UPDATED_AT = 'w_updated_at';
	const EXTENSION_ID = 'w_extension_id';
	const EXTENSION_PARAMS_JSON = 'w_extension_params_json';

	const VIRTUAL_CONTEXT_LINK = '*_context_link';
	const VIRTUAL_HAS_FIELDSET = '*_has_fieldset';
	const VIRTUAL_WATCHERS = '*_workers';
	
	const CONTEXT_LINK = 'cl_context_from';
	const CONTEXT_LINK_ID = 'cl_context_from_id';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'webhook_listener', 'id', $translate->_('common.id')),
			self::NAME => new DevblocksSearchField(self::NAME, 'webhook_listener', 'name', $translate->_('common.name')),
			self::GUID => new DevblocksSearchField(self::GUID, 'webhook_listener', 'guid', $translate->_('common.guid')),
			self::UPDATED_AT => new DevblocksSearchField(self::UPDATED_AT, 'webhook_listener', 'updated_at', $translate->_('common.updated')),
			self::EXTENSION_ID => new DevblocksSearchField(self::EXTENSION_ID, 'webhook_listener', 'extension_id', $translate->_('common.extension')),
			self::EXTENSION_PARAMS_JSON => new DevblocksSearchField(self::EXTENSION_PARAMS_JSON, 'webhook_listener', 'extension_params_json', null),

			self::VIRTUAL_CONTEXT_LINK => new DevblocksSearchField(self::VIRTUAL_CONTEXT_LINK, '*', 'context_link', $translate->_('common.links'), null),
			self::VIRTUAL_HAS_FIELDSET => new DevblocksSearchField(self::VIRTUAL_HAS_FIELDSET, '*', 'has_fieldset', $translate->_('common.fieldset'), null),
			self::VIRTUAL_WATCHERS => new DevblocksSearchField(self::VIRTUAL_WATCHERS, '*', 'workers', $translate->_('common.watchers'), 'WS'),
			
			self::CONTEXT_LINK => new DevblocksSearchField(self::CONTEXT_LINK, 'context_link', 'from_context', null),
			self::CONTEXT_LINK_ID => new DevblocksSearchField(self::CONTEXT_LINK_ID, 'context_link', 'from_context_id', null),
		);
		
		// Custom Fields
		$custom_columns = DevblocksSearchField::getCustomSearchFieldsByContexts(array(
			'cerberusweb.contexts.webhook_listener',
		));
		
		if(!empty($custom_columns))
			$columns = array_merge($columns, $custom_columns);

		// Sort by label (translation-conscious)
		DevblocksPlatform::sortObjects($columns, 'db_label');

		return $columns;
	}
};

class Model_WebhookListener {
	public $id = 0;
	public $name = null;
	public $guid = null;
	public $updated_at = 0;
	public $extension_id = null;
	public $extension_params = array();
	
	/**
	 * 
	 * @return Extension_WebhookListenerEngine
	 */
	function getExtension() {
		return Extension_WebhookListenerEngine::get($this->extension_id);
	}
};

class View_WebhookListener extends C4_AbstractView implements IAbstractView_Subtotals, IAbstractView_QuickSearch {
	const DEFAULT_ID = 'webhook_listeners';

	function __construct() {
		$translate = DevblocksPlatform::getTranslationService();
	
		$this->id = self::DEFAULT_ID;
		$this->name = $translate->_('Webhook Listeners');
		$this->renderLimit = 25;
		$this->renderSortBy = SearchFields_WebhookListener::ID;
		$this->renderSortAsc = true;

		$this->view_columns = array(
			SearchFields_WebhookListener::NAME,
			SearchFields_WebhookListener::GUID,
			SearchFields_WebhookListener::EXTENSION_ID,
			SearchFields_WebhookListener::UPDATED_AT,
		);
		$this->addColumnsHidden(array(
			SearchFields_WebhookListener::EXTENSION_PARAMS_JSON,
			SearchFields_WebhookListener::VIRTUAL_CONTEXT_LINK,
			SearchFields_WebhookListener::VIRTUAL_HAS_FIELDSET,
			SearchFields_WebhookListener::VIRTUAL_WATCHERS,
		));
		
		$this->addParamsHidden(array(
			SearchFields_WebhookListener::EXTENSION_PARAMS_JSON,
		));
		
		$this->doResetCriteria();
	}

	function getData() {
		$objects = DAO_WebhookListener::search(
			$this->view_columns,
			$this->getParams(),
			$this->renderLimit,
			$this->renderPage,
			$this->renderSortBy,
			$this->renderSortAsc,
			$this->renderTotal
		);
		return $objects;
	}
	
	function getDataAsObjects($ids=null) {
		return $this->_getDataAsObjects('DAO_WebhookListener', $ids);
	}
	
	function getDataSample($size) {
		return $this->_doGetDataSample('DAO_WebhookListener', $size);
	}

	function getSubtotalFields() {
		$all_fields = $this->getParamsAvailable(true);
		
		$fields = array();

		if(is_array($all_fields))
		foreach($all_fields as $field_key => $field_model) {
			$pass = false;
			
			switch($field_key) {
				// Fields
				case SearchFields_WebhookListener::EXTENSION_ID:
					$pass = true;
					break;
					
				// Virtuals
				case SearchFields_WebhookListener::VIRTUAL_CONTEXT_LINK:
				case SearchFields_WebhookListener::VIRTUAL_HAS_FIELDSET:
				case SearchFields_WebhookListener::VIRTUAL_WATCHERS:
					$pass = true;
					break;
					
				// Valid custom fields
				default:
					if('cf_' == substr($field_key,0,3))
						$pass = $this->_canSubtotalCustomField($field_key);
					break;
			}
			
			if($pass)
				$fields[$field_key] = $field_model;
		}
		
		return $fields;
	}
	
	function getSubtotalCounts($column) {
		$counts = array();
		$fields = $this->getFields();

		if(!isset($fields[$column]))
			return array();
		
		switch($column) {
			case SearchFields_WebhookListener::EXTENSION_ID:
				$label_map = array();
				$manifests = Extension_WebhookListenerEngine::getAll(false);
				if(is_array($manifests))
				foreach($manifests as $k => $mft) {
					$label_map[$k] = $mft->name;
				}
				
				// [TODO] in / contexts[]
				$counts = $this->_getSubtotalCountForStringColumn('DAO_WebhookListener', $column, $label_map, '=', 'value');
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_CONTEXT_LINK:
				$counts = $this->_getSubtotalCountForContextLinkColumn('DAO_WebhookListener', 'cerberusweb.contexts.webhook_listener', $column);
				break;

			case SearchFields_WebhookListener::VIRTUAL_HAS_FIELDSET:
				$counts = $this->_getSubtotalCountForHasFieldsetColumn('DAO_WebhookListener', 'cerberusweb.contexts.webhook_listener', $column);
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_WATCHERS:
				$counts = $this->_getSubtotalCountForWatcherColumn('DAO_WebhookListener', $column);
				break;
			
			default:
				// Custom fields
				if('cf_' == substr($column,0,3)) {
					$counts = $this->_getSubtotalCountForCustomColumn('DAO_WebhookListener', $column, 'webhook_listener.id');
				}
				
				break;
		}
		
		return $counts;
	}
	
	function getQuickSearchFields() {
		$fields = array(
			'_fulltext' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WebhookListener::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'id' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_NUMBER,
					'options' => array('param_key' => SearchFields_WebhookListener::ID),
				),
			'extension' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WebhookListener::EXTENSION_ID, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'guid' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WebhookListener::GUID, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'name' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_TEXT,
					'options' => array('param_key' => SearchFields_WebhookListener::NAME, 'match' => DevblocksSearchCriteria::OPTION_TEXT_PARTIAL),
				),
			'updated' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_DATE,
					'options' => array('param_key' => SearchFields_WebhookListener::UPDATED_AT),
				),
			'watchers' => 
				array(
					'type' => DevblocksSearchCriteria::TYPE_WORKER,
					'options' => array('param_key' => SearchFields_WebhookListener::VIRTUAL_WATCHERS),
				),
		);
		
		// Add searchable custom fields
		
		$fields = self::_appendFieldsFromQuickSearchContext('cerberusweb.contexts.webhook_listener', $fields, null);
		
		// Sort by keys
		
		ksort($fields);
		
		return $fields;
	}	
	
	function getParamsFromQuickSearchFields($fields) {
		$search_fields = $this->getQuickSearchFields();
		$params = DevblocksSearchCriteria::getParamsFromQueryFields($fields, $search_fields);

		// Handle virtual fields and overrides
		if(is_array($fields))
		foreach($fields as $k => $v) {
			switch($k) {
				// ...
			}
		}
		
		$this->renderPage = 0;
		$this->addParams($params, true);
		
		return $params;
	}
	
	function render() {
		$this->_sanitize();
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);
		$tpl->assign('view', $this);

		// Custom fields
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.webhook_listener');
		$tpl->assign('custom_fields', $custom_fields);

		$tpl->assign('view_template', 'devblocks:cerb.webhooks::webhook_listener/view.tpl');
		$tpl->display('devblocks:cerberusweb.core::internal/views/subtotals_and_view.tpl');
	}

	function renderCriteria($field) {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('id', $this->id);

		switch($field) {
			case SearchFields_WebhookListener::NAME:
			case SearchFields_WebhookListener::GUID:
			case SearchFields_WebhookListener::EXTENSION_ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__string.tpl');
				break;
				
			case SearchFields_WebhookListener::ID:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__number.tpl');
				break;
				
			case 'placeholder_bool':
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__bool.tpl');
				break;
				
			case SearchFields_WebhookListener::UPDATED_AT:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__date.tpl');
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_CONTEXT_LINK:
				$contexts = Extension_DevblocksContext::getAll(false);
				$tpl->assign('contexts', $contexts);
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_link.tpl');
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_HAS_FIELDSET:
				$this->_renderCriteriaHasFieldset($tpl, 'cerberusweb.contexts.webhook_listener');
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_WATCHERS:
				$tpl->display('devblocks:cerberusweb.core::internal/views/criteria/__context_worker.tpl');
				break;
				
			default:
				// Custom Fields
				if('cf_' == substr($field,0,3)) {
					$this->_renderCriteriaCustomField($tpl, substr($field,3));
				} else {
					echo ' ';
				}
				break;
		}
	}

	function renderCriteriaParam($param) {
		$field = $param->field;
		$values = !is_array($param->value) ? array($param->value) : $param->value;

		switch($field) {
			default:
				parent::renderCriteriaParam($param);
				break;
		}
	}

	function renderVirtualCriteria($param) {
		$key = $param->field;
		
		$translate = DevblocksPlatform::getTranslationService();
		
		switch($key) {
			case SearchFields_WebhookListener::VIRTUAL_CONTEXT_LINK:
				$this->_renderVirtualContextLinks($param);
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_HAS_FIELDSET:
				$this->_renderVirtualHasFieldset($param);
				break;
			
			case SearchFields_WebhookListener::VIRTUAL_WATCHERS:
				$this->_renderVirtualWatchers($param);
				break;
		}
	}

	function getFields() {
		return SearchFields_WebhookListener::getFields();
	}

	function doSetCriteria($field, $oper, $value) {
		$criteria = null;

		switch($field) {
			case SearchFields_WebhookListener::NAME:
			case SearchFields_WebhookListener::GUID:
			case SearchFields_WebhookListener::EXTENSION_ID:
				$criteria = $this->_doSetCriteriaString($field, $oper, $value);
				break;
				
			case SearchFields_WebhookListener::ID:
				$criteria = new DevblocksSearchCriteria($field,$oper,$value);
				break;
				
			case SearchFields_WebhookListener::UPDATED_AT:
				$criteria = $this->_doSetCriteriaDate($field, $oper);
				break;
				
			case 'placeholder_bool':
				@$bool = DevblocksPlatform::importGPC($_REQUEST['bool'],'integer',1);
				$criteria = new DevblocksSearchCriteria($field,$oper,$bool);
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_CONTEXT_LINK:
				@$context_links = DevblocksPlatform::importGPC($_REQUEST['context_link'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$context_links);
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_HAS_FIELDSET:
				@$options = DevblocksPlatform::importGPC($_REQUEST['options'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,DevblocksSearchCriteria::OPER_IN,$options);
				break;
				
			case SearchFields_WebhookListener::VIRTUAL_WATCHERS:
				@$worker_ids = DevblocksPlatform::importGPC($_REQUEST['worker_id'],'array',array());
				$criteria = new DevblocksSearchCriteria($field,$oper,$worker_ids);
				break;
				
			default:
				// Custom Fields
				if(substr($field,0,3)=='cf_') {
					$criteria = $this->_doSetCriteriaCustomField($field, substr($field,3));
				}
				break;
		}

		if(!empty($criteria)) {
			$this->addParam($criteria, $field);
			$this->renderPage = 0;
		}
	}
		
	function doBulkUpdate($filter, $do, $ids=array()) {
		@set_time_limit(600); // 10m
	
		$change_fields = array();
		$custom_fields = array();

		// Make sure we have actions
		if(empty($do))
			return;

		// Make sure we have checked items if we want a checked list
		if(0 == strcasecmp($filter,"checks") && empty($ids))
			return;
			
		if(is_array($do))
		foreach($do as $k => $v) {
			switch($k) {
				// [TODO] Implement actions
				case 'example':
					//$change_fields[DAO_WebhookListener::EXAMPLE] = 'some value';
					break;
					
				default:
					// Custom fields
					if(substr($k,0,3)=="cf_") {
						$custom_fields[substr($k,3)] = $v;
					}
					break;
			}
		}

		$pg = 0;

		if(empty($ids))
		do {
			list($objects,$null) = DAO_WebhookListener::search(
				array(),
				$this->getParams(),
				100,
				$pg++,
				SearchFields_WebhookListener::ID,
				true,
				false
			);
			$ids = array_merge($ids, array_keys($objects));
			 
		} while(!empty($objects));

		$batch_total = count($ids);
		for($x=0;$x<=$batch_total;$x+=100) {
			$batch_ids = array_slice($ids,$x,100);
			
			if(!empty($change_fields)) {
				DAO_WebhookListener::update($batch_ids, $change_fields);
			}

			// Custom Fields
			self::_doBulkSetCustomFields('cerberusweb.contexts.webhook_listener', $custom_fields, $batch_ids);
			
			unset($batch_ids);
		}

		unset($ids);
	}
};

class Context_WebhookListener extends Extension_DevblocksContext implements IDevblocksContextProfile, IDevblocksContextPeek { // IDevblocksContextImport
	function getRandom() {
		return DAO_WebhookListener::random();
	}
	
	function profileGetUrl($context_id) {
		if(empty($context_id))
			return '';
	
		$url_writer = DevblocksPlatform::getUrlService();
		$url = $url_writer->writeNoProxy('c=profiles&type=webhook_listener&id='.$context_id, true);
		return $url;
	}
	
	function getMeta($context_id) {
		$webhook_listener = DAO_WebhookListener::get($context_id);
		$url_writer = DevblocksPlatform::getUrlService();
		
		$url = $this->profileGetUrl($context_id);
		$friendly = DevblocksPlatform::strToPermalink($webhook_listener->name);
		
		if(!empty($friendly))
			$url .= '-' . $friendly;
		
		return array(
			'id' => $webhook_listener->id,
			'name' => $webhook_listener->name,
			'permalink' => $url,
		);
	}
	
	// [TODO] Interface
	function getDefaultProperties() {
		return array(
			'updated_at',
		);
	}
	
	function getContext($webhook_listener, &$token_labels, &$token_values, $prefix=null) {
		if(is_null($prefix))
			$prefix = 'Webhook Listener:';
		
		$translate = DevblocksPlatform::getTranslationService();
		$fields = DAO_CustomField::getByContext('cerberusweb.contexts.webhook_listener');

		// Polymorph
		if(is_numeric($webhook_listener)) {
			$webhook_listener = DAO_WebhookListener::get($webhook_listener);
		} elseif($webhook_listener instanceof Model_WebhookListener) {
			// It's what we want already.
		} elseif(is_array($webhook_listener)) {
			$webhook_listener = Cerb_ORMHelper::recastArrayToModel($webhook_listener, 'Model_WebhookListener');
		} else {
			$webhook_listener = null;
		}
		
		// Token labels
		$token_labels = array(
			'_label' => $prefix,
			'extension_id' => $prefix.$translate->_('common.extension'),
			'guid' => $prefix.$translate->_('common.guid'),
			'id' => $prefix.$translate->_('common.id'),
			'name' => $prefix.$translate->_('common.name'),
			'updated_at' => $prefix.$translate->_('common.updated'),
			'record_url' => $prefix.$translate->_('common.url.record'),
		);
		
		// Token types
		$token_types = array(
			'_label' => 'context_url',
			'extension_id' => Model_CustomField::TYPE_SINGLE_LINE,
			'guid' => Model_CustomField::TYPE_SINGLE_LINE,
			'id' => Model_CustomField::TYPE_NUMBER,
			'name' => Model_CustomField::TYPE_SINGLE_LINE,
			'updated_at' => Model_CustomField::TYPE_DATE,
			'record_url' => Model_CustomField::TYPE_URL,
		);
		
		// Custom field/fieldset token labels
		if(false !== ($custom_field_labels = $this->_getTokenLabelsFromCustomFields($fields, $prefix)) && is_array($custom_field_labels))
			$token_labels = array_merge($token_labels, $custom_field_labels);
		
		// Custom field/fieldset token types
		if(false !== ($custom_field_types = $this->_getTokenTypesFromCustomFields($fields, $prefix)) && is_array($custom_field_types))
			$token_types = array_merge($token_types, $custom_field_types);
		
		// Token values
		$token_values = array();
		
		$token_values['_context'] = 'cerberusweb.contexts.webhook_listener';
		$token_values['_types'] = $token_types;
		
		if($webhook_listener) {
			$token_values['_loaded'] = true;
			$token_values['_label'] = $webhook_listener->name;
			$token_values['extension_id'] = $webhook_listener->extension_id;
			$token_values['guid'] = $webhook_listener->guid;
			$token_values['id'] = $webhook_listener->id;
			$token_values['name'] = $webhook_listener->name;
			$token_values['updated_at'] = $webhook_listener->updated_at;
			
			// Custom fields
			$token_values = $this->_importModelCustomFieldsAsValues($webhook_listener, $token_values);
			
			// URL
			$url_writer = DevblocksPlatform::getUrlService();
			$token_values['record_url'] = $url_writer->writeNoProxy(sprintf("c=profiles&type=webhook_listener&id=%d-%s",$webhook_listener->id, DevblocksPlatform::strToPermalink($webhook_listener->name)), true);
		}
		
		return true;
	}

	function lazyLoadContextValues($token, $dictionary) {
		if(!isset($dictionary['id']))
			return;
		
		$context = 'cerberusweb.contexts.webhook_listener';
		$context_id = $dictionary['id'];
		
		@$is_loaded = $dictionary['_loaded'];
		$values = array();
		
		if(!$is_loaded) {
			$labels = array();
			CerberusContexts::getContext($context, $context_id, $labels, $values, null, true);
		}
		
		switch($token) {
			case 'watchers':
				$watchers = array(
					$token => CerberusContexts::getWatchers($context, $context_id, true),
				);
				$values = array_merge($values, $watchers);
				break;
				
			default:
				if(substr($token,0,7) == 'custom_') {
					$fields = $this->_lazyLoadCustomFields($token, $context, $context_id);
					$values = array_merge($values, $fields);
				}
				break;
		}
		
		return $values;
	}
	
	function getChooserView($view_id=null) {
		$active_worker = CerberusApplication::getActiveWorker();

		if(empty($view_id))
			$view_id = 'chooser_'.str_replace('.','_',$this->id).time().mt_rand(0,9999);
	
		// View
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->is_ephemeral = true;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Webhook Listeners';
		$view->renderSortBy = SearchFields_WebhookListener::UPDATED_AT;
		$view->renderSortAsc = false;
		$view->renderLimit = 10;
		$view->renderFilters = false;
		$view->renderTemplate = 'contextlinks_chooser';
		
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function getView($context=null, $context_id=null, $options=array(), $view_id=null) {
		$view_id = !empty($view_id) ? $view_id : str_replace('.','_',$this->id);
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = $view_id;
		$defaults->class_name = $this->getViewClass();
		$view = C4_AbstractViewLoader::getView($view_id, $defaults);
		$view->name = 'Webhook Listeners';
		
		$params_req = array();
		
		if(!empty($context) && !empty($context_id)) {
			$params_req = array(
				new DevblocksSearchCriteria(SearchFields_WebhookListener::CONTEXT_LINK,'=',$context),
				new DevblocksSearchCriteria(SearchFields_WebhookListener::CONTEXT_LINK_ID,'=',$context_id),
			);
		}
		
		$view->addParamsRequired($params_req, true);
		
		$view->renderTemplate = 'context';
		C4_AbstractViewLoader::setView($view_id, $view);
		return $view;
	}
	
	function renderPeekPopup($context_id=0, $view_id='') {
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('view_id', $view_id);
		
		if(empty($context_id) || null == ($model = DAO_WebhookListener::get($context_id))) {
			$model = new Model_WebhookListener();
		}
		
		$tpl->assign('model', $model);
		
		// Custom fields
		
		$custom_fields = DAO_CustomField::getByContext('cerberusweb.contexts.webhook_listener', false);
		$tpl->assign('custom_fields', $custom_fields);
		
		if(!empty($context_id)) {
			$custom_field_values = DAO_CustomFieldValue::getValuesByContextIds('cerberusweb.contexts.webhook_listener', $context_id);
			if(isset($custom_field_values[$context_id]))
				$tpl->assign('custom_field_values', $custom_field_values[$context_id]);
		}
		
		// Webhook listener extensions
		
		$webhook_listener_engines = Extension_WebhookListenerEngine::getAll(true);
		$tpl->assign('webhook_listener_engines', $webhook_listener_engines);

		// Template
		
		$tpl->display('devblocks:cerb.webhooks::webhook_listener/peek.tpl');
	}
	
};
