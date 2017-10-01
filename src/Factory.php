<?php

namespace \DB;

class Factory {

	/**
	 * Creates new connection
	 *
	 * @param ConnectionInterface|string $conn
	 * @return ConnectionInterface
	 */
	static function connect($conn) {
		if ($conn instanceof ConnectionInterface) {
			return $conn;
		} else {
			if ($url = (object)parse_url($conn)) {
				$className = ucfirst(strtolower($url->scheme))."Connection";
				return new $className($conn);
			}
		}
	}
}