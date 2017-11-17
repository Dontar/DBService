<?php

namespace \DB;

interface ConnectionInterface {
	/**
	 * @property string $connectionString
	 *
	 */

	/**
	 * Insert $data into $table
	 *
	 * @param string $table The table to insert into.
	 * @param array $data Data to insert.
	 * @return integer The generated table ID (the value of the key field).
	 */
	function insert(string $table, array $data);

	/**
	 * Update $table with $data.
	 * The key field of the table is determined automatically.
	 *
	 * @param string $table Table to update.
	 * @param array $data Data to update with.
	 * @return integer Number of updated field. Currently only 1 or 0.
	 */
	function update(string $table, array $data);

	/**
	 * Delete from $table.
	 * The key field of the table is determined automatically.
	 *
	 * @param string $table Table to delete from.
	 * @param mixed $id The key value to delete.
	 * @return void
	 */
	function delete(string $table, $id);

	/**
	 * Inserts or updates $data into $table depending if key field is provided in $data.
	 * The key field is determined automatically.
	 *
	 * @param string $table
	 * @param array $data
	 * @return void
	 */
	function merge(string $table, array $data);

	/**
	 * Synchronizes the content of $dataRows with the content of $table where $where.
	 * The comparison is made according to the key field. If key is provided in
	 * the $dataRows is updated, if no key is provided in $dataRows the data is inserted,
	 * if the key is missing from the data the row is deleted from the table.
	 *
	 * @param string $table The table to sync with.
	 * @param array $dataRows Data rows.
	 * Example:
	 * <code>
	 * array(
	 * 		0 => array(
	 * 			"key_field" => "value", //this will be updated
	 * 			"data_1" => "value",
	 * 			"data_2" => "value",
	 * 			"data_3" => "value"
	 * 		),
	 * 		1 => array(
	 * 			// no key_field this will be inserted
	 * 			"data_1" => "value",
	 * 			"data_2" => "value",
	 * 			"data_3" => "value",
	 * 			"data_4" => "value"
	 * 		)
	 * )
	 * </code>
	 * @param string $where The WHERE clause. Only the conditions should be provided without
	 * the "where" keyword i.e. "(field1 = 'value') and (field2 = 'value')".
	 * @param array $params If $where is provided with placeholders
	 * i.e. "(field1 = ?) and (field2 = ?)" this should hold the values of the parameters.
	 * @return void
	 */
	function syncData(string $table, array $dataRows, string $where = null, array $params = null);

	/**
	 * Executes $query and return Generator with result.
	 *
	 * @param string $query
	 * @param array $params
	 * @return \Generator|null
	 */
	function select(string $query, array $params = null);

	/**
	 * Executes $query and return only the first row.
	 *
	 * @param string $query
	 * @param array $params
	 * @return array|null
	 */
	function selectOne(string $query, array $params = null);

	/**
	 * Executes $query and return the first column from the first row.
	 *
	 * @param string $query
	 * @param array $params
	 * @return mixed|null
	 */
	function selectValue(string $query, array $params = null);
}