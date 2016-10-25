<?php
class FBStatement {
	public $queryString;
	private $stmt;
	private $result;
	private $pdo;
	/* Methods */
	public function __construct($stmtId, $resultId, $pdo) {
		$this->stmt = $stmtId;
		$this->result = $resultId;
		$this->pdo = $pdo;
	}

	function __destruct() {
		$this->closeCursor();
	}

	private function handleError() {
		$code = $this->errorCode();
		if ($code !== false) {
			throw new Exception($this->errorInfo(), $code);
		}
	}

	public function bindColumn ($column ,  &$param ,  $type ,  $maxlen , $driverdata ) {}
	public function bindParam ( $parameter , &$variable , $data_type = PDO::PARAM_STR, $length, $driver_options) {}
	public function bindValue (  $parameter , $value , $data_type = PDO::PARAM_STR) {}
	public function closeCursor () {
		if (is_resource($this->stmt)) {
			@ibase_free_query($this->stmt);
		}
		if (is_resource($this->result)) {
			@ibase_free_result($this->result);
		}
	 }
	public function columnCount () {
		return @ibase_num_fields($this->result);
	}
	public function debugDumpParams ( ) {}
	public function errorCode ( ) {
		return $this->pdo->errorCode();
	}
	public function errorInfo ( ) {
		return $this->pdo->errorInfo();
	}
	public function execute ( $input_parameters ) {
		$this->result = @call_user_func_array("ibase_execute", array_merge(array($this->stmt), $input_parameters));
		$this->handleError();
		return true;
	}
	public function fetch ( $fetch_style = 0 , $cursor_orientation = PDO::FETCH_ORI_NEXT , $cursor_offset = 0) {
		$row = @ibase_fetch_assoc($this->result, IBASE_TEXT);
		$this->handleError();
		return is_array($row)?array_change_key_case($row):$row;
	}
	public function fetchAll ( $fetch_style = null, $fetch_argument = null, $ctor_args = array()) {
		$result = array ();
		while(($row = $this->fetch()) !== false) {
			$result[] = $row;
		}
		return $result;
	}
	public function fetchColumn ( $column_number = 0 ) {
		$row = @ibase_fetch_row($this->result, IBASE_TEXT);
		$this->handleError();
		return $row[$column_number];
	}
	public function fetchObject ( $class_name = "stdClass", $ctor_args) {
		$result = @ibase_fetch_assoc($this->result, IBASE_TEXT);
		$this->handleError();
		return (object)array_change_key_case($result);
	}
	public function getAttribute (  $attribute ) {}
	public function getColumnMeta (  $column ) {
		return @ibase_field_info($this->result, $column);
	}
	public function nextRowset ( ) {}
	public function rowCount ( ) {
		return @ibase_affected_rows($this->pdo->dbInstance);
	}
	public function setAttribute (  $attribute , $value ) {}
	public function setFetchMode ( $mode ) {}
}

class FBPDO {
	public $dbInstance;
	private $inTrans;
	private $lastTrans;

	private $attrb = array ();

	function __construct ( $dsn , $username , $password , $options = null) {
		if (gettype($dsn) !== 'string') {
			$this->dbInstance = $dsn;
		} else {
			$opts = DBUtils::parseDsn($dsn);
			$this->dbInstance = @ibase_connect($opts['host'].(!empty($opts['port'])?"/".$opts['port']:"").":".$opts['dbname'], $username, $password);
			if ($this->dbInstance === false) {
				$this->handleError();
			}
		}
	}

	function __destruct() {
		@ibase_close($this->dbInstance);
	}

	private function handleError() {
		$code = $this->errorCode();
		if ($code !== false) {
			throw new Exception($this->errorInfo(), $code);
		}
	}

	public function beginTransaction ( $args = IBASE_READ | IBASE_COMMITTED | IBASE_REC_VERSION | IBASE_NOWAIT) {
		$this->lastTrans = @ibase_trans($args, $this->dbInstance);
		$this->handleError();
		return $this->lastTrans;
	}
	public function commit () {
		$result = @ibase_commit($this->lastTrans);
		$this->lastTrans = null;
		$this->handleError();
		return $result;
	}
	public function errorCode ( ) {
		return @ibase_errcode();
	}
	public function errorInfo (  ) {
		return @ibase_errmsg();
	}
	public function exec ( $statement ) {
		@ibase_query($statement);
		$this->handleError();
		return @ibase_affected_rows($this->dbInstance);
	}
	public function getAttribute ( $attribute ) {
		return $this->attrb[$attribute];
	}
	public static function getAvailableDrivers () {}
	public function inTransaction ( ) {
		return $this->inTrans;
	}
	public function lastInsertId ($name = NULL) {
		return @ibase_gen_id($name, 0, $this->dbInstance);
	}
	// PDOStatement
	public function prepare ( $statement , $driver_options = array() ) {
		$result = @ibase_prepare($this->dbInstance, $statement);
		$this->handleError();
		return new FBStatement($result, null, $this);
	}
	// PDOStatement
	public function query ( $statement ) {
		$result = @ibase_query($this->dbInstance, $statement);
		$this->handleError();
		return new FBStatement(null, $result, $this);
	}
	public function quote ( $string , $parameter_type = PDO::PARAM_STR ) {
		return "'$string'";
	}
	public function rollBack ( ) {
		$result = @ibase_rollback($this->lastTrans);
		$this->lastTrans = null;
		$this->handleError();
		return $result;
	}
	public function setAttribute (  $attribute , $value ) {
		$this->attrb[$attribute] = $value;
	}
}

class DBUtils {

	static function fbConnStrToUlr($string, $user = "", $pass = "") {
		$match = array ();
		if (preg_match("/(.+?)(\/(\d{2,4}))*\:(.+)/", $string, $match)) {
			return "firebird://".
				(!empty($user)?$user.":".$pass."@":"").
				$match[1].
				($match[3]?":".$match[3]:"").
				"/".$match[4];
		} else if (preg_match("/\/(\d{2,4})\:(.+)/", $string, $match)) {
			return "firebird://".(!empty($user)?$user.":".$pass."@":"")."localhost".
				($match[1]?":".$match[1]:"").
				"/".$match[2];
		} else {
			return "firebird://".
			(!empty($user)?$user.":".$pass."@":"").
			"localhost/".$string;
		}
	}

	static function parseDsn($dsn) {
		$result = array ();
		$opts = explode(":", $dsn);
		$opts[1] = explode(";", $opts[1]);

		foreach ($opts[1] as $value) {
			$t = explode("=", $value);
			$result[$t[0]] = $t[1];
		}
		return $result;
	}

	static function sqlDate($dbType, $from, $to, $field) {
		if (!empty($from)) {
			if (!empty($to)) {
				return "($field BETWEEN '$from' AND ('$to' + 1))";

			} else {
				return "($field >= '$from')";
			}

		} else
			return '';
	}

	static function fullText($dbType, $vector, $value = '?') {
		return "(to_tsvector(array_to_string(array[".implode(',',$vector)."], ' ')) @@ to_tsquery('$value'))";
	}

	static function fuzzyText($dbType, $vector, $value = '?') {
		$vector = array_map(function($item) {
			return "COALESCE($item, '')";
		}, $vector);
		return "(((".implode("||", $vector).") <-> $value) <= 0.99)";
	}

	static function ftQuery($dbType, $query) {
		$query = implode(" ", array_filter(explode(' ', $query)));
		$query = str_replace(" без ", " !", $query);
		$query = str_replace(" или ", "|", $query);
		$query = str_replace(" и ", "&", $query);
		$query = str_replace(" ", "&", $query);

		return $query;
	}

	static private $keySql = array (
		"pgsql" => "SELECT a.attname as pkey FROM pg_index i JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) WHERE i.indrelid = '{table}'::regclass AND i.indisprimary;",
		"mysql" => "SHOW KEYS FROM {table} WHERE `Key_name` = 'PRIMARY' and `Seq_in_index` = 1",
		"firebird" => 'select s.rdb$field_name from rdb$indices i left join rdb$index_segments s on i.rdb$index_name = s.rdb$index_name left join rdb$relation_constraints rc on rc.rdb$index_name = i.rdb$index_name where rc.rdb$constraint_type = \'PRIMARY KEY\' and lower(i.rdb$relation_name) = lower(\'{table}\')'
	);

	static function getPKey($dbType, $table) {
		$pkey = "";
		$sql = str_replace("{table}", $table, self::$keySql[$dbType]);
		switch ($dbType) {
			case 'mysql':
				$pkey = DBService::selectOne($sql2)['column_name'];
				break;
			default:
				$pkey = DBService::selectValue($sql);
				break;
		}

		return !empty($pkey)?$pkey:"item_id";
	}
}


class DBService {
	static $dbUrl = "pgsql://postgres:police256@localhost/micsy";

	/**
	*
	* @var PDO
	*/
	private static $dbInstance;

	public static $connParams;

	static private function processParams($params) {
		return array_map(function($item) {
			if (is_bool($item)) {
				return $item?"true":"false";
			}
			if (is_string($item)) {
				return strlen($item) > 0?$item:null;
			}
			return $item;
		}, $params);
	}

	static private function getPKey($table) {
		self::getDB();
		return DBUtils::getPKey(self::$connParams['scheme'], $table);
	}

	static private function cleanFields($table, $data) {
		$result = array();
		$cursor = self::getDB()->query("select * from $table where 0 = 1");
		$cols = $cursor->columnCount();
		for($i = 0;$i < $cols;$i++) {
			$colMeta = $cursor->getColumnMeta($i);
			$name = strtolower($colMeta['name']);
			if (array_key_exists($name, $data)) {
				$result[$name] = $data[$name];
			}
		}
		return $result;
	}

	/**
	*
	* @return PDO
	*/
	static function getDB() {
		if (! isset ( self::$dbInstance )) {
			$connParams = parse_url ( self::$dbUrl );
			self::$connParams = $connParams;
			$dsn = array ();
			foreach ( $connParams as $key => $value ) {
				switch ($key) {
					case "host" :
						$dsn [] = "host=$value";
						break;
					case "port" :
						$dsn [] = "port=$value";
						break;
					case "path" :
						$value = basename($value);
						$dsn [] = "dbname=$value";
						break;
					case "user" :
						$dsn [] = "user=$value";
						break;
					case "pass" :
						$dsn [] = "password=$value";
						break;
					default :
						;
						break;
				}
			}
			$dsn = $connParams['scheme'].":".implode(";", $dsn);
			if ($connParams['scheme'] == "firebird") {
				self::setDB(new FBPDO($dsn, $connParams['user'], $connParams['pass']));
			} else {
				self::setDB(new PDO($dsn, $connParams['user'], $connParams['pass']));
			}
		}
		return self::$dbInstance;
	}

	static function initDB() {
		self::$dbInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		self::$dbInstance->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		self::$dbInstance->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
		self::$dbInstance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

		// self::$dbInstance->exec("SET NAMES 'UTF8'");
			// self::getDB()->exec("SELECT set_limit(0.80)");
	}

	static function setDB($pdo) {
		self::$dbInstance = $pdo;
		self::initDB();
	}

	static function insertOrUpdate($table, $data) {
		$key = self::getPKey($table);
		if (isset($data[$key])) {
			self::update($table, $data);
			return $data[$key];
		}
		return self::insert($table, $data);
	}

	static function insert($table, $data) {
		$key = self::getPKey($table);
		// unset($data[$key]);
		$data = self::cleanFields($table, $data);
		$fields = array_keys($data);
		// $values = array_map(function($item) {return !empty($item)?$item:null;}, array_values($data));
		$values = array_values($data);
		$sql = "INSERT INTO $table (".implode(", ", $fields).") VALUES (".str_repeat("?,", count($values) - 1)."?)";

		// if (isset($data[$key]) && $data[$key] !== null) {
		// 	self::select($sql, $values);
		// 	return $data[$key];
		// }
		switch (self::$connParams['scheme']) {
			case 'mysql':
				self::exec($sql, $values);
				return (!empty($key))?self::selectValue("SELECT LAST_INSERT_ID()"):0;
			case 'pgsql':
				return self::selectValue($sql.(!empty($key))?" RETURNING $key":"", $values);
			default:
				return self::exec($sql, $values);
		}
	}

	static function update($table, $data) {
		$id = self::getPKey($table);
		$idValue = $data[$id];
		if (!isset($idValue) || $idValue === null) {
			throw new Exception("Key field $id for table $table not specified!");
		}
		unset($data[$id]);
		$data = self::cleanFields($table, $data);
		$fields = array_keys($data);
		// $values = array_map(function($item) {return !empty($item)?$item:null;}, array_values($data));
		$values = array_values($data);
		$values[] = $idValue;

		$sql = "UPDATE $table SET ".implode(" = ?,", $fields)." = ? WHERE ($id = ?)";
		$cursor = self::getDB()->prepare($sql);
		return $cursor->execute(self::processParams($values));
	}

	static function delete($table, $value) {
		$id = self::getPKey($table);
		$sql = "delete from $table where $id = ?";
		$cursor = self::getDB()->prepare($sql);
		return $cursor->execute(array($value));
	}

	static function select($sql, $params = null) {
		if (empty($params)) {
			return self::getDB()->query($sql)->fetchAll();
		} else {
			$cursor = self::getDB()->prepare($sql);
			$cursor->execute(self::processParams($params));
			return $cursor->fetchAll();
		}
	}
	static function exec($sql, $params = null) {
		if (empty($params)) {
			self::getDB()->query($sql);
		} else {
			$cursor = self::getDB()->prepare($sql);
			$cursor->execute(self::processParams($params));
		}
	}

	static function selectCursor($sql, $params = null) {
		if (empty($params)) {
			return self::getDB()->query($sql);
		} else {
			$cursor = self::getDB()->prepare($sql);
			$cursor->execute(self::processParams($params));
			return $cursor;
		}
	}

	static function selectOne($sql, $params = null) {
		if (empty($params)) {
			return self::getDB()->query($sql)->fetch();
		} else {
			$cursor = self::getDB()->prepare($sql);
			$cursor->execute(self::processParams($params));
			return $cursor->fetch();
		}
	}

	static function selectValue($sql, $params = null) {
		if (empty($params)) {
			return self::getDB()->query($sql)->fetchColumn();
		} else {
			$cursor = self::getDB()->prepare($sql);
			$cursor->execute(self::processParams($params));
			return $cursor->fetchColumn();
		}
	}

	static private function array_column($a, $col) {
		return array_map(function($item) use ($col) {return $item[$col];}, $a);
	}

	static function syncWithArray($table, $data, $where) {
		$id = self::getPKey($table);

		$currentContentSQL = "SELECT $id FROM $table WHERE $where";
		$currentContent = self::array_column(self::select($currentContentSQL), $id);
		$incomigData = self::array_column($data, $id);
		$rowsForDelete = array_diff($currentContent, $incomigData);

		foreach ($rowsForDelete as $keyValue) {
			self::delete($table, $keyValue);
		}

		return array_map(function($row) use ($id, $table) {
			$row[$id] = self::insertOrUpdate($table, $row);
			return $row;
		}, $data);
	}

	static function merge($table, $data) {
		$key = self::getPKey($table);
		$data[$key] = self::insertOrUpdate($table, $data);
		return $data;
	}

	static function sqlDate($from, $to, $field) {
		return DBUtils::sqlDate(self::$connParams['scheme'], $from, $to, $field);
	}

	static function fullText($vector, $value = '?') {
		return DBUtils::fullText(self::$connParams['scheme'], $vector, $value);
	}

	static function fuzzyText($vector, $value = '?') {
		return DBUtils::fuzzyText(self::$connParams['scheme'], $vector, $value);
	}

	static function ftQuery($query) {
		return DBUtils::ftQuery(self::$connParams['scheme'], $query);
	}

}
