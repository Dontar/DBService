<?php

namespace DB;

use DB\Drivers\MultiConnection;
use DB\Utils\Where;

class Factory {

	/**
	 * Creates new connection
	 *
	 * @param string|array $conn
	 * @return ConnectionInterface
	 */
	static function connect($conn) {
		if (is_array($conn)) {
			return new MultiConnection($conn);
		} else if ($conn instanceof ConnectionInterface) {
			return $conn;
		} else {
			if ($url = (object)parse_url($conn)) {
				$className = "DB\\Drivers\\".ucfirst(strtolower($url->scheme))."Connection";
				return new $className($conn);
			}
		}
	}

	/**
	 * Undocumented function
	 *
	 * @param string $exp
	 * @return Where
	 */
	static function where($exp) {
		return (new Where())->and($exp);
	}

	/**
	 * Undocumented function
	 *
	 * @param array $filter
	 * @return Where
	 */
	static function filter($filter) {
		return (new Where($filter));
	}
}
