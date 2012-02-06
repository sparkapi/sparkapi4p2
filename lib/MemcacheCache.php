<?php

class flexmlsAPI_MemcacheCache implements flexmlsAPI_CacheInterface {
	protected $cache = null;
	
	protected $host = null;
	protected $port = null;
	
	
	function __construct($host = 'localhost', $port = 11211) {
		$this->host = $host;
		$this->port = $port;
		$this->cache = new Memcache;
		$this->cache->connect($host, $port);
	}

	function get($key) {
		$value = $this->cache->get($key);
		if ($value !== false) {
			return $value;
		}
		return null;
	}

	function set($key, $value, $expire) {
		return $this->cache->set($key, $value, 0, $expire);
	}


}
