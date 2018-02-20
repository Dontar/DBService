<?php

namespace DB\Utils;

/**
 * Undocumented class
 *
 * @method Where and($expression)
 * @method Where or($expression)
 *
 * @method Where get()
 *
 * @method Where eq($value)
 * @method Where lt($value)
 * @method Where gt($value)
 * @method Where ltq($value)
 * @method Where gtq($value)
 * @method Where in(mixed $list)
 * @method Where bt($val1, $val2)
 * @method Where like($value)
 * @method Where is($value)
 * @method Where isNot($value)
 * @method Where isNull();
 * @method Where isNotNull();
 */
class Where {

	private $expMap = [
		"in" => "IN",
		"like" => "LIKE",
		'eq' => '=',
		'neq' => '<>',
		'lt' => "<",
		'ltq' => "<=",
		'gt' => ">",
		'gtq' => ">=",
		'bt' => "BETWEEN",
		'is' => "IS",
		'isNot' => 'IS NOT',
		'isNull' => "IS",
		'isNotNull' => 'IS NOT'
	];

	private $expressions = [];

	private $filter;

	function __construct($filter = null) {
		$this->filter = $filter;
	}

	function __call($name, array $args) {
		switch ($name) {
			case 'and':
			case 'or':
				$lexp = "";
				if (empty($args) && !empty($this->expressions)) {
					$cond = &$this->expressions[count($this->expressions) - 1];
					$lexp = $cond[1];
				} else {
					$lexp = $args[0];
				}
				$this->expressions[] = [
					strtoupper($name),
					$lexp, "", ""
				];
				break;
			case "get":
				$result = [];
				$esc = function($v) {
					if (is_numeric($v) || empty($v) || "NULL" === strtoupper($v)) {
						return $v;
					}
					if (is_bool($v)) {
						return $v ? 1 : 0;
					}
					return "'".str_replace("'", "''", $v)."'";
				};

				foreach($this->expressions as $cond) {
					list($c, $lexp, $op, $rexp) = $cond;

					list($fField, $dbField) = (count($exp = explode(" as ", $lexp)) > 1)?$exp:[$lexp, $lexp];

					if (!array_key_exists($fField, $this->filter)) continue;

					switch ($op) {
						case 'IN':
							$rexp = is_array($rexp) ? "(".implode(", ", array_map($esc, $rexp)).")" : "(".$rexp.")";
							break;
						case 'BETWEEN':
							$rexp = implode(" AND ", array_map($esc, $rexp));
							break;
						default:
							$rexp = $esc($rexp);
					}
					$result[] = trim(sprintf("%s (%s %s %s)", !empty($result) ? $c : "", $dbField, $op, $rexp));
				}
				return implode(" ", count($result) ? $result : ["(1 = 1)"]);
			default:
				$len = count($args);
				$cond = &$this->expressions[count($this->expressions) - 1];
				$cond[2] = $this->expMap[$name];
				if ($this->filter && !$len) {
					list($fField) = explode(" as ", $cond[1]);
					$cond[3] = array_key_exists($fField, $this->filter) ? $this->filter[$fField] : "NULL";
				} else {
					$cond[3] = ($len) ? (($len > 1) ? $args : $args[0]) : "NULL";
				}
				break;
		}
		return $this;
	}

	function __toString() {
		return $this->get();
	}
}
