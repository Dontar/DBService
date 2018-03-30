<?php

namespace DB\Drivers;

use DB\Base\Connection;

class Oci8Connection extends Connection
{

	function connect($uri = null)
	{
		$uri = empty($uri) ? $this->connectionString : $uri;
		$opts = parse_url($uri);
		parse_str($opts['query'], $params);
		$args = [
			$opts['user'],
			$opts['pass'],
			sprintf(
				"%s:%d/%s",
				$opts['host'],
				empty($opts['port']) ? "3307" : $opts['port'],
				trim($opts['path'], "/")
			)
		];
		if (!empty($params) && !empty($params['charset'])) {
			$args[] = $params['charset'];
		}

		$self = $this;
		$this->handleError(function () use (&$self, &$args) {
			$self->db = call_user_func_array("oci_connect", $args);
		});
		$this->connected = true;
	}

	protected function getPKey($table)
	{
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

	protected function getColumns($tables)
	{
		$result = [];
		$stmt = oci_parse($this->db, "select * from $table where (0 = 1)");
		oci_execute($stmt);
		$len = oci_num_fields($stmt);
		for ($i = 0; $i < $len; $i++) {
			$result[] = oci_field_name($stmt, $i);
		}
		return $result;
	}

	function exec($query, array $params = null)
	{
		if (!$this->connected) $this->connect();
		if (!empty($params)) {
			$query = preg_replace_callback("/\?/", function () use (&$params) {
				return "'" . str_replace("'", "''", array_shift($params)) . "'";
			}, $query);
		}
		$stmt = $this->handleError(function ($db) use (&$query) {
			oci_execute($stmt = oci_parse($db, $query));
			return $stmt;
		});

		while (($row = oci_fetch_assoc($stmt)) !== false) {
			yield array_change_key_case($row);
		}
	}
}
