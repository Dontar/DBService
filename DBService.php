<?php
class DBService {
    static $dbUrl = "pgsql://postgres:police256@localhost/micsy";

   /**
    *
    * @var PDO
    */
    private static $dbInstance;

	public static $connParams;

    static function processParams($params) {
        return array_map(function($item) {
            if (is_bool($item)) {
                return $item?"true":"false";
            }
            if (is_string($item)) {
                return strlen($item) > 0?$item:null;
            }
            return $item;
        }, $params);
    }

    static function getPKey($table) {
        self::getDB();
		$pkey = "";
        //postgres
        $sql = <<<SQL
SELECT a.attname as pkey
FROM   pg_index i
JOIN   pg_attribute a ON a.attrelid = i.indrelid
                     AND a.attnum = ANY(i.indkey)
WHERE  i.indrelid = '$table'::regclass
AND    i.indisprimary;
SQL;
        //mysql
        $sql2 = "SHOW KEYS FROM $table WHERE `Key_name` = 'PRIMARY' and `Seq_in_index` = 1";

		switch (self::$connParams['scheme']) {
			case 'mysql':
				$pkey = self::selectOne($sql2)['column_name'];
				break;
			default:
				$pkey = self::selectValue($sql);
				break;
		}

        return !empty($pkey)?$pkey:"item_id";
    }

    static function cleanFields($table, $data) {
        $result = array();
        $cursor = self::getDB()->query("select * from $table where 0 = 1");
        $cols = $cursor->columnCount();
        for($i = 0;$i < $cols;$i++) {
            $colMeta = $cursor->getColumnMeta($i);
            if (array_key_exists($colMeta['name'], $data)) {
                $result[$colMeta['name']] = $data[$colMeta['name']];
            }
        }
        return $result;
    }

    /**
    *
    * @return PDO
    */
    static function getDB() {
        if (! isset ( self::$dbInstance )) {
            $connParams = parse_url ( self::$dbUrl );
			self::$connParams = $connParams;
            $dsn = array ();
            foreach ( $connParams as $key => $value ) {
                switch ($key) {
                    case "host" :
                        $dsn [] = "host=$value";
                        break;
                    case "port" :
                        $dsn [] = "port=$value";
                        break;
                    case "path" :
                        $value = basename($value);
                        $dsn [] = "dbname=$value";
                        break;
                    case "user" :
                        $dsn [] = "user=$value";
                        break;
                    case "pass" :
                        $dsn [] = "password=$value";
                        break;
                    default :
                        ;
                        break;
                }
            }
            $dsn = $connParams['scheme'].":".implode(";", $dsn);
            self::setDB(new PDO($dsn, $connParams['user'], $connParams['pass']));
        }
        return self::$dbInstance;
    }

    static function initDB() {
        self::$dbInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$dbInstance->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        self::$dbInstance->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_EMPTY_STRING);
        self::$dbInstance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::$dbInstance->exec("SET NAMES 'UTF8'");
            // self::getDB()->exec("SELECT set_limit(0.80)");
    }

    static function setDB($pdo) {
        self::$dbInstance = $pdo;
        self::initDB();
    }

    static function insertOrUpdate($table, $data) {
        $key = self::getPKey($table);
        if (isset($data[$key])) {
            self::update($table, $data);
            return $data[$key];
        }
        return self::insert($table, $data);
    }

    static function insert($table, $data) {
        $key = self::getPKey($table);
        // unset($data[$key]);
        $data = self::cleanFields($table, $data);
        $fields = array_keys($data);
        // $values = array_map(function($item) {return !empty($item)?$item:null;}, array_values($data));
        $values = array_values($data);
        $sql = "INSERT INTO $table (".implode(", ", $fields).") VALUES (".str_repeat("?,", count($values) - 1)."?)";

		if (isset($data[$key]) && $data[$key] !== null) {
			self::select($sql, $values);
			return $data[$key];
		}
		switch (self::$connParams['scheme']) {
			case 'mysql':
				self::exec($sql, $values);
				return (!empty($key))?self::selectValue("SELECT LAST_INSERT_ID()"):0;
			case 'pgsql':
				return self::selectValue($sql.(!empty($key))?" RETURNING $key":"", $values);
			default:
				return self::selectValue($sql, $values);
		}
    }

    static function update($table, $data) {
        $id = self::getPKey($table);
        $idValue = $data[$id];
        if (!isset($idValue) || $idValue === null) {
            throw new Exception("Key field $id for table $table not specified!");
        }
        unset($data[$id]);
        $data = self::cleanFields($table, $data);
        $fields = array_keys($data);
        // $values = array_map(function($item) {return !empty($item)?$item:null;}, array_values($data));
        $values = array_values($data);
        $values[] = $idValue;

        $sql = "UPDATE $table SET ".implode(" = ?,", $fields)." = ? WHERE ($id = ?)";
        $cursor = self::getDB()->prepare($sql);
        return $cursor->execute(self::processParams($values));
    }

    static function delete($table, $value) {
        $id = self::getPKey($table);
        $sql = "delete from $table where $id = ?";
        $cursor = self::getDB()->prepare($sql);
        return $cursor->execute(array($value));
    }

    static function select($sql, $params = null) {
        if (empty($params)) {
            return self::getDB()->query($sql)->fetchAll();
        } else {
            $cursor = self::getDB()->prepare($sql);
            $cursor->execute(self::processParams($params));
            return $cursor->fetchAll();
        }
    }
    static function exec($sql, $params = null) {
        if (empty($params)) {
            self::getDB()->query($sql);
        } else {
            $cursor = self::getDB()->prepare($sql);
            $cursor->execute(self::processParams($params));
        }
    }

    static function selectCursor($sql, $params = null) {
        if (empty($params)) {
            return self::getDB()->query($sql);
        } else {
            $cursor = self::getDB()->prepare($sql);
            $cursor->execute(self::processParams($params));
            return $cursor;
        }
    }

    static function selectOne($sql, $params = null) {
        if (empty($params)) {
            return self::getDB()->query($sql)->fetch();
        } else {
            $cursor = self::getDB()->prepare($sql);
            $cursor->execute(self::processParams($params));
            return $cursor->fetch();
        }
    }

    static function selectValue($sql, $params = null) {
        if (empty($params)) {
            return self::getDB()->query($sql)->fetchColumn();
        } else {
            $cursor = self::getDB()->prepare($sql);
            $cursor->execute(self::processParams($params));
            return $cursor->fetchColumn();
        }
    }

    static function array_column($a, $col) {
        return array_map(function($item) use ($col) {return $item[$col];}, $a);
    }

    static function syncWithArray($table, $data, $where) {
        $id = self::getPKey($table);

        $currentContentSQL = "SELECT $id FROM $table WHERE $where";
        $currentContent = self::array_column(self::select($currentContentSQL), $id);
        $incomigData = self::array_column($data, $id);
        $rowsForDelete = array_diff($currentContent, $incomigData);

        foreach ($rowsForDelete as $keyValue) {
            self::delete($table, $keyValue);
        }

        return array_map(function($row) use ($id, $table) {
            $row[$id] = self::insertOrUpdate($table, $row);
            return $row;
        }, $data);
    }

    static function merge($table, $data) {
        $key = self::getPKey($table);
        $data[$key] = self::insertOrUpdate($table, $data);
        return $data;
    }

    static function sqlDate($from, $to, $field) {
        if (!empty($from)) {
            if (!empty($to)) {
//                 $from = date('Y-m-d', strtotime($from));
//                 $to = strtotime($to);
                return "($field BETWEEN '$from' AND '$to' + interval '1 day')";

            } else {
                return "($field >= '$from')";
            }

        } else
            return '';
    }

    static function fullText($vector, $value = '?') {
        return "(to_tsvector(array_to_string(array[".implode(',',$vector)."], ' ')) @@ to_tsquery('$value'))";
    }

    static function fuzzyText($vector, $value = '?') {
        $vector = array_map(function($item) {
            return "COALESCE($item, '')";
        }, $vector);
        return "(((".implode("||", $vector).") <-> $value) <= 0.99)";
    }

    static function ftQuery($query) {
        $query = implode(" ", array_filter(explode(' ', $query)));
        $query = str_replace(" без ", " !", $query);
        $query = str_replace(" или ", "|", $query);
        $query = str_replace(" и ", "&", $query);
        $query = str_replace(" ", "&", $query);

        return $query;
    }

}
