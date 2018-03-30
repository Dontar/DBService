<?php

namespace DB\Drivers;

use DB\Base\Connection;

class FirebirdConnection extends Connection
{

	function connect($uri = null)
	{
		$uri = empty($uri) ? $this->connectionString : $uri;
		$opts = parse_url($uri);
		$self = $this;
		$this->handleError(function() use (&$opts, &$self) {
			parse_str($opts['query'], $params);
			$self->db = ibase_connect(
				sprintf(
					"%s/%d:%s",
					$opts['host'],
					!empty($opts['port']) ? $opts['port'] : "3050",
					trim($opts['path'], "/")
				),
				$opts['user'],
				$opts['pass'],
				$params['charset']?$params['charset']:"UTF8"
			);
		});
		$this->connected = true;
	}

	protected function getPKey($table)
	{
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

	protected function getColumns($table)
	{
		$result = [];
		$stmt = ibase_query("select * from $table where (0 = 1)");
		$len = ibase_num_fields($stmt);
		for ($i = 0; $i < $len; $i++) {
			$meta = ibase_field_info($stmt, $i);
			$result[] = $meta['name'];
		}
		return $result;
	}

	function exec($query, array $params = null)
	{
		if (!$this->connected) $this->connect();
		$stmt = $this->handleError(function($db) use (&$query, &$params) {
			if (empty($params)) {
				return ibase_query($db, $query);
			} else {
				$q = ibase_prepare($db, $query);
				array_unshift($params, $q);
				return call_user_func_array("ibase_execute", $params);
			}
		});
		while (($row = ibase_fetch_assoc($stmt)) !== false) {
			yield $row;
		}
	}

}
