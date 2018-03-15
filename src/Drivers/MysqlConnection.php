<?php

namespace DB\Drivers;
use DB\Base\Connection;

class MysqlConnection extends Connection {

	public $db;

	function __construct($conn) {
		$opts = parse_url($conn);

		$this->db = mysql_connect(
			sprintf(
				"%s:%d",
				$opts['host'],
				empty($opts['port'])?"3307":$opts['port']
			),
			$opts['user'], $opts['pass']
		);
	}

	protected function getPKey($table) {
		$sql = "SHOW KEYS FROM $table WHERE `Key_name` = 'PRIMARY' and `Seq_in_index` = 1";
		return array_column(iterator_to_array($this->select($sql)), "column_name");
	}

	protected function getColumns($tables) {
		$result = [];
		$stmt = mysql_query("select * from $table where (0 = 1)");
		$len = mysql_num_fields($stmt);
		for($i = 0; $i < $len; $i++) {
			$result[] = mysql_field_info($stmt, $i);
		}
		return $result;
	}

	function exec($query, array $params = null) {
		if (!empty($params)) {
			$query = preg_replace_callback("/\?/", function() use (&$params) {
				return "'".mysql_escape_string(array_shift($params))."'";
			}, $query);
		}
		$stmt = mysql_query($query, $this->db);
		while(($row = mysql_fetch_assoc($stmt)) !== FALSE) {
			yield $row;
		}
	}
}
