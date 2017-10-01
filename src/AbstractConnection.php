<?php

namespace \DB;

abstract class AbstractConnection implements ConnectionInterface {

	/**
	 * @var PDO
	 */
	protected $driver;

	/**
	 * @var string
	 */
	public $connectionString;

	protected function urlToPDO(string $connStr) {
		$cn = parse_url($connStr);
		$dsn = array();
		foreach ( $cn as $key => $value ) {
			switch ($key) {
				case "host" :
					$dsn [] = "host=$value";
					break;
				case "port" :
					$dsn [] = "port=$value";
					break;
				case "path" :
					$value = substr($value, 1);
					$dsn [] = "dbname=$value";
					break;
				case "user" :
					$dsn [] = "user=$value";
					break;
				case "pass" :
					$dsn [] = "password=$value";
					break;
				default :
					break;
			}
		}		
		return $cn['scheme'].":".implode(";", $dsn);
	}

	abstract protected function getPKey($table);

	protected function cleanParams($params) {
		return array_map(function($item) {
			if (is_bool($item)) {
				return $item?"1":"0";
			}
			if (is_string($item) && $item == "null") {
				return null;
			}
			if (is_string($item)) {
				return strlen($item) > 0?$item:null;
			}
			return $item;
		}, $params);
	}

	protected function cleanFields($table, $data) {
		$result = array();
		$cursor = $this->driver->query("select * from $table where 0 = 1");
		$cols = $cursor->columnCount();
		for($i = 0;$i < $cols;$i++) {
			$colMeta = $cursor->getColumnMeta($i);
			$name = strtolower($colMeta['name']);
			if (array_key_exists($name, $data)) {
				$result[$name] = $data[$name];
			}
		}
		return $result;
	}

	protected function generateInsertSQL(string $table, array $fields) {
		return "INSERT INTO $table (".implode(", ", $fields).") VALUES (".str_repeat("?,", count($values) - 1)."?)";
	}

	/**
	 * {@inheritDoc}
	 */
	function insert(string $table, array $data) {
		$data = $this->cleanFields($table, $data);
		$params = $this->cleanParams(array_values($data));
		$fields = array_keys($data);
		$sql = $this->generateInsertSQL($table, $fields);

		return $this->selectValue($sql, $params);
	}

	/**
	 * {@inheritDoc}
	 */
	function update(string $table, array $data) {
		$keyName = $this->getPKey($table);
		$idValue = $data[$keyName];
		if (!isset($idValue) || $idValue === null) {
			throw new Exception("Key field $id for table $table not specified!");
		}
		unset($data[$keyName]);


		$data = $this->cleanFields($table, $data);
		$fields = array_keys($data);
		$params = $this->cleanParams(array_values($data));
		$params[] = $idValue;

		$sql = "UPDATE $table SET ".implode(" = ?,", $fields)." = ? WHERE ($keyName = ?)";
		return $this->exec($sql, $params);
	}

	/**
	 * {@inheritDoc}
	 */
	function delete(string $table, $id) {
		$keyName = $this->getPKey($table);
		$sql = "delete from $table where $keyName = ?";
		return $this->exec($sql);
	}

	/**
	 * {@inheritDoc}
	 */
	function merge(string $table, array $data) {
		$keyName = $this->getPKey($table);
		if (isset($data[$keyName]) && $data[$keyName] !== null) {
			$this->update($table, $data);
			return $data[$keyName];
		}
		return $this->insert($table, $data);
	}

	/**
	 * {@inheritDoc}
	 */
	function syncData(string $table, array $dataRows, string $where = null, array $params = null) {
		$keyName = $this->getPKey($table);
		
		$currentContentSQL = "SELECT $id FROM $table WHERE $where";
		$currentContent = $this->driver->query($currentContentSQL)->fetchAll(PDO::FETCH_COLUMN);
		$incomingData = array_map(function($item) use ($keyName) {return $item[$keyName];}, $dataRows);
		$rowsForDelete = array_diff($currentContent, $incomingData);

		foreach ($rowsForDelete as $keyValue) {
			$this->delete($table, $keyValue);
		}

		return array_map(function($row) use ($id, $table) {
			$row[$id] = $this->merge($table, $row);
			return $row;
		}, $data);
	}

	/**
	 * {@inheritDoc}
	 */
	function select(string $query, array $params = null) {
		$noSelect = strpos(strtolower($query), 'select') === false;
		$stmt = null;
		if (empty($params)) {
			$stmt = $this->driver->query($noSelect?"select * from $query":$query);
		} else {
			if ($noSelect) {
				$query = "select * from $query where ".implode(" and ", array_map(function($k) {return "($k = ?)";}, array_keys($params)));
				$stmt = $this->driver->prepare($query);
				$stmt->execute($this->cleanParams(array_values($params)));
				
			} else {
				$stmt = $this->driver->prepare($query);
				$stmt->execute($this->cleanParams($params));
			}
		}
		while (($row = $stmt->fetch()) !== false) {
			yield $row;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	function selectOne(string $query, array $params = null) {
		$stmt = null;
		if (empty($params)) {
			$stmt = $this->driver->query($query);
		} else {
			$stmt = $this->driver->prepare($query);
			$stmt->execute($this->cleanParams($params));
		}
		return $stmt->fetch();
	}

	/**
	 * {@inheritDoc}
	 */
	function selectValue(string $query, array $params = null) {
		if (empty($params)) {
			return $this->driver->query($query)->fetchColumn();
		} else {
			$stmt = $this->driver->prepare($query);
			$stmt->execute(self::processParams($params));
			return $stmt->fetchColumn();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	function exec(string $query, array $params = null) {
		if (empty($params)) {
			return $this->driver->exec($query);
		} else {
			$stmt = $this->driver->prepare($query);
			return $stmt->execute($params);
		}
	}

	function beginTransaction() {
		return $this->driver->beginTransaction();
	}
	
	function commit() {
		return $this->driver->commit();
	}
	
	function rollback() {
		return $this->driver->rollBack();
	}
	
	function sqlDate($from, $to, $field) {
		if (!empty($from)) {
			if (!empty($to)) {
				return "($field BETWEEN '$from' AND ('$to' + 1))";

			} else {
				return "($field >= '$from')";
			}

		} else
			return '';
	}
}