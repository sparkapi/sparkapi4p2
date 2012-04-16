<?php

class SparkAPI_APIAuth extends SparkAPI_Core implements SparkAPI_AuthInterface {
	protected $api_key = null;
	protected $api_secret = null;


	function __construct($api_key, $api_secret) {
		$this->api_key = $api_key;
		$this->api_secret = $api_secret;
		
		$this->auth_mode = 'api';
		
		parent::__construct();
	}
	
	function is_auth_request($request) {
		return ($request['uri'] == '/'. $this->api_version .'/session') ? true : false;
	}


	function sign_request($request) {

		$http_parameters = $request['params'];
		$cache_http_parameters = $http_parameters;
		
		$sec_string = "{$this->api_secret}ApiKey{$this->api_key}";
		$post_body = "";
		
		if ($request['method'] == "POST" && !empty($request['post_data']) > 0) {
			// the request is to post some JSON data back to the API (like adding a contact)
			$post_body = $request['post_data'];
		}
		
		$is_auth_request = ($request['uri'] == '/'. $this->api_version .'/session') ? true : false;
		
		if ($is_auth_request) {
			$http_parameters['ApiKey'] = $this->api_key;
		}
		else {
			
			if ( empty($this->last_token) ) {
				// attempt to pull this from the cache if it's turned on
				if ($this->cache) {
					$cached_token = $this->cache->get($this->cache_prefix . 'authtoken');
					if ($cached_token != null) {
						$this->SetAuthToken($cached_token);
					}
				}
			}
			
			$http_parameters['AuthToken'] = $this->last_token;

			// since this isn't an authentication request, add the ServicePath to the security string
			$sec_string .= "ServicePath" . rawurldecode($request['uri']);

			ksort($http_parameters);

			// add each of the HTTP query string parameters to the security string
			foreach ($http_parameters as $k => $v) {
				$sec_string .= $k . $v;
			}
		}
		
		if (!empty($post_body)) {
			// add the post data to the end of the security string if it exists
			$sec_string .= $post_body;
		}
		
		// calculate the security string as ApiSig
		$api_sig = md5($sec_string);
		$http_parameters['ApiSig'] = $api_sig;

		$request['query_string'] = http_build_query($http_parameters);
		$request['cacheable_query_string'] = http_build_query($cache_http_parameters);

		return $request;

	}
	
	function SetAuthToken($token) {
		$this->last_token = $token;
	}
	

	/*
	 * Authentication
	 */

	function Authenticate() {
		$response = $this->MakeAPICall("POST", "session");
		
		if ($response['success']) {
			$this->last_token = $response['results'][0]['AuthToken'];
			$this->last_token_expire = $response['results'][0]['Expires'];
			
			if ($this->cache) {
				$this->cache->set($this->cache_prefix . 'authtoken', $this->last_token, 86400);
			}
			
			if ( is_callable($this->access_change_callback) ) {
				call_user_func($this->access_change_callback, 'api', array('auth_token' => $this->last_token) );
			}
			
			return true;
		}
		
		return false;
	}
	
	function ReAuthenticate() {
		return $this->Authenticate();
	}
	
	

}
