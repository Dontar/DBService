<?php

namespace \DB;

class MysqlConnection extends AbstractConnection {
	function __construct($conn) {
		$this->connectionString = $conn;
		parent::__construct($this->urlToPDO($conn));
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		$this->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
		$this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}

	protected function getPKey($table) {
		$sql = "SHOW KEYS FROM $table WHERE `Key_name` = 'PRIMARY' and `Seq_in_index` = 1";
		return strtolower(trim($this->selectOne($sql)['column_name']));
	}

}