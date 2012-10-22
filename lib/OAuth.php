<?php

class SparkAPI_OAuth extends SparkAPI_Core implements SparkAPI_AuthInterface {
	const DEFAULT_AUTH_ENDPOINT = "https://sparkplatform.com/";
	const DEVELOPERS_AUTH_ENDPOINT = "https://developers.sparkplatform.com/";

	protected $force_https = true;
	protected $api_client_id = null;
	protected $api_client_secret = null;
	protected $oauth_redirect_uri = null;
	public $oauth_access_token = null;
	public $oauth_refresh_token = null;
	protected $oauth_grant_code = null;

	function __construct($api_client_id, $api_client_secret, $redirect_uri, $access_token = null) {
		$this->api_client_id = $api_client_id;
		$this->api_client_secret = $api_client_secret;
		$this->oauth_redirect_uri = $redirect_uri;
		$this->SetAccessToken($access_token);
		
		$this->auth_mode = 'oauth';
		
		parent::__construct();
	}

	function authentication_endpoint_uri($additional_params = array()) {
		$params = array(
			"response_type" => "code",
			"client_id"     => $this->api_client_id,
			"redirect_uri"  => $this->oauth_redirect_uri
		);
		return $this->authentication_host() . "oauth2?" . http_build_query(array_merge($params, $additional_params));
	}

	function sign_request($request) {
		$this->SetHeader('Authorization', 'OAuth '. $this->last_token);

		// reload headers into request
		$request['headers'] = $this->headers;
		$request['query_string'] = http_build_query($request['params']);
		$request['cacheable_query_string'] = $request['query_string'];

		return $request;

	}
	
	function is_auth_request($request) {
		return ($request['uri'] == '/'. $this->api_version .'/oauth2/grant') ? true : false;
	}
	
	function Grant($code, $type = 'authorization_code') {
		$body = array(
			'client_id' => $this->api_client_id,
			'client_secret' => $this->api_client_secret,
		 	'grant_type' => $type,
			'redirect_uri' => $this->oauth_redirect_uri
		);
		
		if ($type == 'authorization_code') {
			$body['code'] = $code;
		}
		if ($type == 'refresh_token') {
			$body['refresh_token'] = $code;
		}
		
		$response = $this->MakeAPICall("POST", "oauth2/grant", '0s', array(), json_encode($body) );
		
		if ($response['success'] == true) {
			$this->SetAccessToken( $response['results']['access_token'] );
			$this->SetRefreshToken( $response['results']['refresh_token'] );
			
			if ( is_callable($this->access_change_callback) ) {
				call_user_func($this->access_change_callback, 'oauth', array('access_token' => $this->oauth_access_token, 'refresh_token' => $this->oauth_refresh_token) );
			}
			
			return true;
		}
		else {
			return false;
		}
		
	}
	
	function SetAccessToken($token) {
		$this->oauth_access_token = $token;
		$this->last_token = $token;
	}
	
	function SetRefreshToken($token) {
		$this->oauth_refresh_token = $token;
	}


	/*
	 * Authentication
	 */

	function Authenticate() {
		return true;
	}
	
	function ReAuthenticate() {
		if ( !empty($this->oauth_refresh_token) ) {
			return $this->Grant($this->oauth_refresh_token, 'refresh_token');
		}
		return false;
	}
	
	function Ping() {
		return $this->return_all_results( $this->MakeAPICall("GET", "my/account") );
	}

	protected function authentication_host() {
		if ($this->developer_mode == true) {
			return self::DEVELOPERS_AUTH_ENDPOINT;
		}
		else {
			return self::DEFAULT_AUTH_ENDPOINT;
		}
	}

}
