<?php

namespace \DB;

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
		array_unshift($input_parameters, $this->stmt);
		$this->result = @call_user_func_array("ibase_execute", $input_parameters);
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

	private $attrb = array (
		PDO::ATTR_DRIVER_NAME => "firebird"
	);

	private function parseDsn($dsn) {
		$result = array ();
		$opts = explode(";", $dsn);
		$opts[0] = explode(":", $opts[0])[1];

		foreach ($opts as $value) {
			$t = explode("=", $value);
			$result[$t[0]] = $t[1];
		}
		return $result;
	}

	function __construct ( $dsn , $username = null , $password = null , $options = null) {
		if (gettype($dsn) !== 'string') {
			$this->dbInstance = $dsn;
		} else {
			$opts = $this->parseDsn($dsn);
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

class FirebirdConnection extends AbstractConnection {
	function __construct($conn) {
		$this->connectionString = $conn;
		$dsn = $this->urlToPDO($conn);

		if (!extension_loaded("PDO_Firebird")) {
			$this->driver = new FBPDO($dsn, $cn['user'], $cn['pass']);
		} else {
			$this->driver = new PDO($dsn, $cn['user'], $cn['pass']);
		}
	
		$this->driver->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->driver->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		$this->driver->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
		$this->driver->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}

	protected function getPKey($table) {
		$sql = str_replace('{table}', $table, 'select s.rdb$field_name from rdb$indices i left join rdb$index_segments s on i.rdb$index_name = s.rdb$index_name left join rdb$relation_constraints rc on rc.rdb$index_name = i.rdb$index_name where rc.rdb$constraint_type = \'PRIMARY KEY\' and lower(i.rdb$relation_name) = lower(\'{table}\')');
		return strtolower(trim($this->selectValue($sql)));
	}

	protected function generateInsertSQL(string $table, array $fields) {
		$keyName = $this->getPKey($table);
		return "INSERT INTO $table (".implode(", ", $fields).") VALUES (".str_repeat("?,", count($values) - 1)."?) RETURNING $key";
	}
}