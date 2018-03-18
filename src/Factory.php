<?php

namespace DB;

use DB\Drivers\MultiConnection;
use DB\Utils\Where;

class Factory {

	static private $currentConn;

	/**
	 * Creates new connection
	 *
	 * @param string|array $conn
	 * @return ConnectionInterface
	 */
	static function connect($conn = null) {
		if (is_array($conn)) {
			return self::$currentConn = new MultiConnection($conn);
		} else if ($conn instanceof ConnectionInterface) {
			return self::$currentConn = $conn;
		} else if (is_string($conn)) {
			if ($scheme = parse_url($conn, PHP_URL_SCHEME)) {
				$className = "DB\\Drivers\\".ucfirst(strtolower($scheme))."Connection";
				return self::$currentConn = new $className($conn);
			}
		} else {
			return self::$currentConn;
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
