<?php

class SparkAPI_WordPressCache implements SparkAPI_CacheInterface {

	
	function get($key) {
		$value = get_transient($key);
		if ($value !== false) {
			return $value;
		}
		return null;
	}

	function set($key, $value, $expire) {
		return set_transient($key, $value, $expire);
	}


}
