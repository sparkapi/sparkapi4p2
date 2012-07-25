<?php

class SparkAPI_MySQLiCache implements SparkAPI_CacheInterface {
	protected $cache = null;
	
	protected $hostname = null;
	protected $database = null;
	protected $username = null;
	protected $password = null;
	protected $table_name = null;
	protected $conn = null;
	
	
	function __construct($hostname = 'localhost', $database = '', $username = '', $password = '', $table_name = 'api_cache') {

		// check if $hostname given is actually an object (pre-existing mysqli connection)
		if ( is_object($hostname)) {
			$this->conn = $hostname;
		}
		else {
			// make a new connection to the database
			$this->conn = new mysqli($hostname, $username, $password, $database);
			if ($this->conn->connect_error) {
				$this->conn = null;
			}
		}

		$this->table_name = $table_name;

	}

	function get($key) {
		// check if
		if (!$this->conn) {
			return null;
		}

		$this->check_gc();

		$sql = "SELECT `cache_value`
				FROM `". $this->table_name ."`
				WHERE `cache_key` = '". $this->quote($key) ."' AND `expiration` > unix_timestamp()
				LIMIT 1";

		if ($result = $this->conn->query($sql)) {
			$row = $result->fetch_assoc();
			if ($row !== null) {
				return unserialize($row['cache_value']);
			}
		}
		return false;
	}

	function set($key, $value, $expire) {
		if (!$this->conn) {
			return null;
		}

		$this->check_gc();

		$value = serialize($value);

		$new_expire = time() + $expire;

		$sql = "INSERT INTO `". $this->table_name ."` (`cache_key`, `cache_value`, `expiration`)
				VALUES ('". $this->quote($key) ."', '". $this->quote($value) ."', ". $this->quote($new_expire) .")
				ON DUPLICATE KEY UPDATE `cache_value`='". $this->quote($value) ."', `expiration`=". $this->quote($new_expire);

		return $this->conn->query($sql);
	}

	function quote($value) {
		return $this->conn->real_escape_string($value);
	}

	function check_gc() {
		$random_number = rand(1, 1000);
		if ($random_number === 1) {
			$this->gc();
		}
	}

	function gc() {
		return $this->conn->query("DELETE FROM `". $this->table_name ."` WHERE `expiration` < unit_timestamp()");
	}


}
