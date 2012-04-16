<?php

interface SparkAPI_CacheInterface {

	function get($key);
	function set($key, $value, $expire);

}
