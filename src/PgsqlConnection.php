<?php

namespace \DB;

class PgsqlConnection extends AbstractConnection {
	function __construct($conn) {
		$this->connectionString = $conn;
		$this->driver = new PDO($this->urlToPDO($conn));
		$this->driver->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->driver->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		$this->driver->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
		$this->driver->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}

	protected function getPKey($table) {
		$sql = "SELECT a.attname as pkey FROM pg_index i JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) WHERE i.indrelid = '{$table}'::regclass AND i.indisprimary;";
		return strtolower(trim($this->selectValue($sql)));
	}

	protected function generateInsertSQL(string $table, array $fields) {
		$keyName = $this->getPKey($table);
		return "INSERT INTO $table (".implode(", ", $fields).") VALUES (".str_repeat("?,", count($values) - 1)."?) RETURNING $key";
	}

	function fullText($vector, $value = '?') {
		return "(to_tsvector(array_to_string(array[".implode(',',$vector)."], ' ')) @@ to_tsquery('$value'))";
	}

	function fuzzyText($vector, $value = '?') {
		$vector = array_map(function($item) {
			return "COALESCE($item, '')";
		}, $vector);
		return "(((".implode("||", $vector).") <-> $value) <= 0.99)";
	}

	function ftQuery($query) {
		$query = implode(" ", array_filter(explode(' ', $query)));
		$query = str_replace(" без ", " !", $query);
		$query = str_replace(" или ", "|", $query);
		$query = str_replace(" и ", "&", $query);
		$query = str_replace(" ", "&", $query);

		return $query;
	}
}