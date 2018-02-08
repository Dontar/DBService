<?php

namespace DB\Drivers;
use DB\Base;

class FirebirdConnection extends AbstractConnection {

	public $db;

	function __construct($conn) {
		$opts = parse_url($conn);

		$this->db = ibase_connect(
			sprintf("%s/%s:%d",
				$opts['host'],
				!empty($opts['port'])?$opts['port']:"3050",
				trim($opts['path'],"/")
			),
			$opts['user'], $opts['pass']
		);
	}

	protected function getPKey($table) {
		$sql = <<<SQL
select
	s.rdb\$field_name as thekey
from rdb$indices i
left join rdb\$index_segments s on i.rdb\$index_name = s.rdb\$index_name
left join rdb\$relation_constraints rc on rc.rdb\$index_name = i.rdb\$index_name
where
	rc.rdb\$constraint_type = 'PRIMARY KEY' and
	lower(i.rdb\$relation_name) = lower('$table')
SQL;
		return array_column(iterator_to_array($this->select($sql)), "thekey");
	}

	protected function getColumns($table) {
		$result = [];
		$stmt = ibase_query("select * from $table where (0 = 1)");
		$len = ibase_num_fields($stmt);
		for($i = 0; $i < $len; $i++) {
			$meta = ibase_field_info($stmt, $i);
			$result[] = $meta['name'];
		}
		return $result;
	}

	function exec($query, $params = null) {
		$stmt = null;
		if (empty($params)) {
			$stmt = ibase_query($this->db, $query);
		} else {
			$q = ibase_prepare($this->db, $query);
			array_unshift($params, $q);
			$stmt = call_user_func_array("ibase_execute", $params);
		}
		while(($row = ibase_fetch_assoc($stmt)) !== FALSE) {
			yield $row;
		}
	}

}
