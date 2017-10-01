<?php

namespace \DB;

class MysqlConnection extends AbstractConnection {
	function __construct($conn) {
		$this->connectionString = $conn;
		$this->driver = new PDO($this->urlToPDO($conn));
		$this->driver->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->driver->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		$this->driver->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
		$this->driver->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}

	protected function getPKey($table) {
		$sql = "SHOW KEYS FROM $table WHERE `Key_name` = 'PRIMARY' and `Seq_in_index` = 1";
		return strtolower(trim($this->selectOne($sql)['column_name']));
	}

	protected function generateInsertSQL(string $table, array $fields) {
		$keyName = $this->getPKey($table);
		return "INSERT INTO $table (".implode(", ", $fields).") VALUES (".str_repeat("?,", count($values) - 1)."?) RETURNING $key";
	}
}