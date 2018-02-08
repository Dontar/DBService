<?php

namespace DB;

use DB\Drivers\MultiConnection;

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
				$className = ucfirst(strtolower($url->scheme))."Connection";
				return new $className($conn);
			}
		}
	}
}
