<?php

namespace DB\Base;

use DB\ConnectionInterface;

abstract class Connection implements ConnectionInterface {

	/**
	 * @var string
	 */
	public $connectionString;

	/**
	 * Finds the name of PK column for $table.
	 *
	 * @param string $table
	 * @return array
	 */
	abstract protected function getPKey($table);

	/**
	 * Get columns of $table
	 *
	 * @param string $table
	 * @return void
	 */
	abstract protected function getColumns($table);

	/**
	 * Trys to clean params values ie. booleans, null's, strings etc.
	 *
	 * @param array $params
	 * @return array
	 */
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

	/**
	 * Rmoves does fields from $data that do not exists in $table
	 *
	 * @param string $table
	 * @param array $data
	 * @return void
	 */
	protected function cleanFields($table, $data) {
		$result = array();
		$cols = $this->getColumns($table);
		foreach($cols as $name) {
			if (array_key_exists($name, $data)) {
				$result[$name] = $data[$name];
			}
		}
		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	function insert($table, array $data) {
		$data = $this->cleanFields($table, $data);
		$params = $this->cleanParams(array_values($data));
		$fields = array_keys($data);
		$keyNames = $this->getPKey($table);
		return $this->selectValue("INSERT INTO $table (".implode(", ", $fields).") VALUES (".str_repeat("?,", count($values) - 1)."?) RETURNING ".implode(", ", $keyNames), $params);
	}

	/**
	 * {@inheritDoc}
	 */
	function update($table, array $data) {
		$keyNames = $this->getPKey($table);
		$idValues = array_intersect_key($data, array_fill_keys($keyNames, NULL));
		$data = array_diff_key($data, $idValues);

		$data = $this->cleanFields($table, $data);
		$fields = array_keys($data);
		$params = $this->cleanParams(array_values($data));
		$params = array_merge($params, $idValues);

		$sql = "UPDATE $table SET ".implode(" = ?,", $fields)." = ? WHERE (".implode(" = ?", $keyNames).")";
		return $this->exec($sql, $params);
	}

	/**
	 * {@inheritDoc}
	 */
	function delete($table, array $ids) {
		$keyNames = $this->getPKey($table);
		$sql = "delete from $table where ".implode(" = ?", $keyNames);
		return $this->exec($sql, $ids);
	}

	/**
	 * {@inheritDoc}
	 */
	function merge($table, array $data) {
		$keyNames = $this->getPKey($table);
		$idValues = array_intersect_key($data, array_fill_keys($keyNames, NULL));
		if (count($idValues) == 0) {
			return $this->insert($table, $data);
		}
		$this->update($table, $data);
		return $idValues;
	}

	/**
	 * {@inheritDoc}
	 */
	function syncData($table, array $dataRows, $where = null, array $params = null) {
		$keyNames = $this->getPKey($table);

		$currentContentSQL = "SELECT ".implode(" || '-' || ", $keyNames)." as THEKEY FROM $table".(!empty($where)?" WHERE $where":"");
		$currentContent = array_column(iterator_to_array($this->exec($currentContentSQL)), "THEKEY");
		$incomingData = array_map(function($row) use ($keyNames) {
			$idValues = array_intersect_key($row, array_fill_keys($keyNames, NULL));
			return implode("-", array_values($idValues));
		});
		$rowsForDelete = array_diff($currentContent, $incomingData);

		foreach ($rowsForDelete as $keyValue) {
			$this->delete($table, explode("-", $keyValue));
		}

		return array_map(function($row) use ($keyNames, $table) {
			$ids = $this->merge($table, $row);
			return array_merge($row, $ids);
		}, $dataRows);
	}

	/**
	 * {@inheritDoc}
	 */
	function select($query, array $params = null) {
		if (strpos(strtolower($query), 'select ') === false) {
			$query = "select * from $query where ".implode(" and ", array_map(function($k) {return "($k = ?)";}, array_keys($params)));
		}
		return $this->exec($query, array_values($params));
	}

	/**
	 * {@inheritDoc}
	 */
	function selectOne($query, array $params = null) {
		$result = $this->exec($query, array_values($params));
		if (!empty($result)) {
			return $result[0];
		}
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	function selectValue($query, array $params = null) {
		$result = $this->exec($query, array_values($params));
		if (!empty($result)) {
			list($value) = array_values($result[0]);
			return $value;
		}
		return null;
	}

	/**
	 * The backbone of the lib
	 *
	 * @param string $query
	 * @param array $params
	 * @return \Generator|null|mixed
	 */
	abstract public function exec($query, array $params = null);

	/**
	 * Undocumented function
	 *
	 * @param string $from
	 * @param string $to
	 * @param string $field
	 * @return void
	 */
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
