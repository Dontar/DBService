<?php

namespace DB\Drivers;
use DB\Base\Connection;

class PgsqlConnection extends Connection {
	function __construct($conn) {
		$opts = parse_url($conn);

		$this->db = pg_connect(
			sprintf(
				"host=%s port=%d dbname=%s user=%s password=%s options='--client_encoding=UTF8'",
				$opts['host'],
				empty($opts['port'])?"5432":$opts['port'],
				trim($opts['path'], "/"),
				$opts['user'],
				$opts['pass']
			)
		);
	}

	protected function getPKey($table) {
		$sql = <<<SQL
SELECT a.attname as thekey
FROM pg_index i
JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
WHERE i.indrelid = '{$table}'::regclass AND i.indisprimary;
SQL;
		return array_column(iterator_to_array($this->select($sql)), "thekey");
	}

	protected function getColumns($tables) {
		$result = [];
		$stmt = pg_query($this->db, "select * from $table where (0 = 1)");
		$len = pg_num_fields($stmt);
		for($i = 0; $i < $len; $i++) {
			$result[] = pg_field_name($stmt, $i);
		}
		return $result;
	}

	function exec($query, array $params = null) {
		$stmt = null;
		if (!empty($params)) {
			$p = array_keys($params);
			$query = preg_replace_callback("/\?/", function() use (&$p) {
				return "$".array_shift($p);
			}, $query);
			$stmt = pg_query_params($this->db, $query, $params);
		} else {
			$stmt = pg_query($this->db, $query);
		}
		while(($row = pg_fetch_assoc($stmt)) !== FALSE) {
			yield $row;
		}
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
