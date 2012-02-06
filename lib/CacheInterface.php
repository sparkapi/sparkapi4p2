<?php

interface flexmlsAPI_CacheInterface {

	function get($key);
	function set($key, $value, $expire);

}
