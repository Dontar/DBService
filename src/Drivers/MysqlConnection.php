<?php

namespace DB\Drivers;

use DB\Base\Connection;

class MysqlConnection extends Connection
{

	function connect($uri = null)
	{
		$uri = empty($uri) ? $this->connectionString : $uri;
		$opts = parse_url($uri);

		$self = $this;
		$this->handleError(function () use (&$opts, &$self) {
			$self->db = mysql_connect(
				sprintf(
					"%s:%d",
					$opts['host'],
					empty($opts['port']) ? "3307" : $opts['port']
				),
				$opts['user'],
				$opts['pass']
			);
		});
		$this->connected = true;
	}

	protected function getPKey($table)
	{
		$sql = "SHOW KEYS FROM $table WHERE `Key_name` = 'PRIMARY' and `Seq_in_index` = 1";
		return array_column(iterator_to_array($this->select($sql)), "column_name");
	}

	protected function getColumns($tables)
	{
		$result = [];
		$stmt = mysql_query("select * from $table where (0 = 1)");
		$len = mysql_num_fields($stmt);
		for ($i = 0; $i < $len; $i++) {
			$result[] = mysql_field_info($stmt, $i);
		}
		return $result;
	}

	function exec($query, array $params = null)
	{
		if (!$this->connected) $this->connect();
		if (!empty($params)) {
			$query = preg_replace_callback("/\?/", function () use (&$params) {
				return "'" . mysql_escape_string(array_shift($params)) . "'";
			}, $query);
		}
		$stmt = $this->handleError(function ($db) use (&$query) {
			return mysql_query($query, $db);
		});
		while (($row = mysql_fetch_assoc($stmt)) !== false) {
			yield $row;
		}
	}
}
