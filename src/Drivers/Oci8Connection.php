<?php

namespace DB\Drivers;
use DB\Base;

class Oci8Connection extends AbstractConnection {

	public $db;

	function __construct($conn) {
		$opts = parse_url($conn);

		$this->db = oci_connect(
			$opts['user'], $opts['pass'],
			sprintf(
				"%s:%d/%s",
				$opts['host'],
				empty($opts['port'])?"3307":$opts['port'],
				trim($opts['path'], "/")
			)
		);
	}

	protected function getPKey($table) {
		$sql = <<<SQL
SELECT cols.column_name as thekey
FROM all_constraints cons, all_cons_columns cols
WHERE cols.table_name = upper('$table')
AND cons.constraint_type = 'P'
AND cons.constraint_name = cols.constraint_name
AND cons.owner = cols.owner
ORDER BY cols.table_name, cols.position;
SQL;
		return array_column(iterator_to_array($this->select($sql)), "thekey");
	}

	protected function getColumns($tables) {
		$result = [];
		$stmt = oci_parse($this->db, "select * from $table where (0 = 1)");
		oci_execute($stmt);
		$len = oci_num_fields($stmt);
		for($i = 0; $i < $len; $i++) {
			$result[] = oci_field_name($stmt, $i);
		}
		return $result;
	}

	function exec($query, $params = null) {
		if (!empty($params)) {
			$query = preg_replace_callback("/\?/", function() use (&$params) {
				return "'".str_replace("'", "''", array_shift($params))."'";
			}, $query);
		}
		$stmt = oci_parse($this->db, $query);
		oci_execute($stmt);
		while(($row = oci_fetch_assoc($stmt)) !== FALSE) {
			yield $row;
		}
	}
}
