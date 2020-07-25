<?

namespace DB;

class Db {

	private static $db = null;
	private $mysqli;
	private $sym_query = "{?}";

		public static function getDB() {
			if (self::$db == null) self::$db = new Db();
			return self::$db;
		}

		private function __construct() {
			global $config;
			$this->mysqli = new \mysqli($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
			$this->mysqli->query("SET lc_time_names = 'ru_RU'");
			$this->mysqli->query("SET NAMES 'utf8mb4'");
		}

		private function getQuery($query, $params) {
			if ($params) {
				for ($i = 0; $i < count($params); $i++) {
					$pos = strpos($query, $this->sym_query);
					$arg = "'" . $this->mysqli->real_escape_string($params[$i]) . "'";
					$query = substr_replace($query, $arg, $pos, strlen($this->sym_query));
				}
			}
			return $query;
		}

		public function select($query, $params = false) {
			$result_set = $this->mysqli->query($this->getQuery($query, $params));
			if (!$result_set) return $this->mysqli->error;
			return $this->resultSetToArray($result_set);
		}

		public function query($query, $params = false) {
			$success = $this->mysqli->query($this->getQuery($query, $params));
			if ($success) {
				if ($this->mysqli->insert_id === 0) return true;
				else return $this->mysqli->insert_id;
			} else return $this->mysqli->error;
		}

		private function resultSetToArray($result_set) {
			$array = array();
			while (($row = $result_set->fetch_assoc()) != false) {
				$array[] = $row;
			}
			return $array;
		}

		public function __destruct() {
			if ($this->mysqli) $this->mysqli->close();
		}

}

?>