<?php

class flexmlsAPI_CurlTransport extends flexmlsAPI_CoreTransport implements flexmlsAPI_TransportInterface {
	protected $ch = null;
	
	function __construct() {
		// initialize cURL for use later
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_HEADER, false);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->ch, CURLOPT_ENCODING, "gzip");
	}
	
	function __destruct() {
		// clean cURL up
		curl_close($this->ch);
	}
	
	function make_request($request = array()) {
		
//		print_r($request);
				
		$request_headers_flat = "";
		foreach ($request['headers'] as $k => $v) {
			$request_headers_flat .= "{$k}: {$v}\r\n";
		}
		
		$full_url  = $request['protocol'] .'://'. $request['host'] . $request['uri'];
		if ( !empty($request['query_string']) ) {
			$full_url .= '?'. $request['query_string'];
		}
		
		curl_setopt($this->ch, CURLOPT_URL, $full_url);

		if ($request['method'] == "POST") {
			curl_setopt($this->ch, CURLOPT_POST, 1);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request['post_data']);
		}
		else {
			curl_setopt($this->ch, CURLOPT_POST, 0);
		}
		
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(trim($request_headers_flat)));
		
		$response_body = curl_exec($this->ch);
		$response_info = curl_getinfo($this->ch);
		
		$response_info['body'] = $response_body;
		
//		print_r($response_info);

		return $response_info;

	}

}