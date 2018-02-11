<?php

namespace DB\Drivers;
use DB\ConnectionInterface;

class MultiConnection implements ConnectionInterface {

	/**
	 * Undocumented variable
	 *
	 * @var ConnectionInterface[]
	 */
	protected $drivers;

	/**
	 * Undocumented function
	 *
	 * @param string[] $conns
	 */
	function __construct(array $conns) {
		$this->drivers = [];
		foreach ($conns as $conn) {
			$this->drivers[] = Factory::connect($conn);
		}
	}

	function __call($name, $args) {
		if (strpos(strtolower($name), 'select') !== false) {
			return call_user_func_array([$this->drivers[0], $name], $args);
		} else {
			foreach ($this->drivers as $driver) {
				yield call_user_func_array([$driver, $name], $args);
			}
		}
	}

}
