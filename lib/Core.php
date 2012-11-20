<?php

/**
 * A PHP wrapper for the Spark REST API
 *
 * Version: 2.0
 *
 * Source URI: https://github.com/sparkapi/sparkapi4p2
 * Author: (c) Financial Business Systems, Inc. 2011, 2012
 * Author URI: http://sparkplatform.com/docs
 *
 * This file is part of the Spark PHP API client..

 *     The Spark PHP API client is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     (at your option) any later version.

 *     The Spark PHP API client is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.

 *     You should have received a copy of the GNU General Public License
 *     along with the Spark PHP API client.  If not, see <http://www.gnu.org/licenses/>.
 */


spl_autoload_register(array('SparkAPI_Core', 'autoload'));


class SparkAPI_Core {
	const DEFAULT_API_BASE = "sparkapi.com";
	const DEFAULT_PLATFORM_BASE = "sparkplatform.com";

  /* 
    We'll no longer advertise a seperate domain for development,
    but will keep the logic around for backwards compat.
   */
	const DEVELOPERS_API_BASE = "sparkapi.com";
	
	public $api_client_version = '2.0';

	public $api_base = self::DEFAULT_API_BASE;
	public $platform_base = self::DEFAULT_PLATFORM_BASE;
	public $api_version = "v1";

	private $debug_mode = false;
	private $debug_log = null;
	protected $developer_mode = false;
	protected $force_https = false;
	protected $transport = null;
	protected $cache = null;
	protected $cache_prefix = "SparkAPI_";

	protected $headers = array();

	public $last_token = null;
	protected $access_change_callback = null;

	public $auth_mode = null;

	public $last_count = null;
	public $total_pages = null;
	public $current_page = null;
	public $page_size = null;

	public $last_updated = null;

	public $last_error_code = null;
	public $last_error_mess = null;


	/*
	 * Core functions
	 */

	function __construct() {
		$this->SetHeader("Content-Type", "application/json");
		$this->SetHeader('User-Agent', 'Spark API PHP Client/' . $this->api_client_version);
	}

	static function autoload($class_name) {
		if (preg_match('/^SparkAPI/', $class_name)) {
			$file_name = preg_replace('/^SparkAPI\_/', '', $class_name);
			include_once(dirname(realpath(__FILE__)) . '/' . $file_name . '.php');
		}
	}

	function SetApplicationName($name) {
		$this->SetHeader('X-SparkApi-User-Agent', str_replace(array("\r", "\r\n", "\n"), '', trim($name)));
	}

	function SetDebugMode($mode = false) {
		$this->debug_mode = $mode;
	}

	function SetDeveloperMode($enable = false) {
		$this->developer_mode = $enable;
		if ($enable) {
			$this->api_base = self::DEVELOPERS_API_BASE;
		}
		else {
			$this->api_base = self::DEFAULT_API_BASE;
		}
		return $enable;
	}

	function SetTransport($transport) {
		if (is_object($transport)) {
			$this->transport = $transport;
		}
		else {
			throw new Exception("SetTransport() called but value isn't a valid transport object");
		}
	}

	function SetCache($cache) {
		if (is_object($cache)) {
			$this->cache = $cache;
		}
		else {
			throw new Exception("SetCache() called but value isn't a valid cache object");
		}
	}

	function SetCachePrefix($prefix) {
		$this->cache_prefix = $prefix;
		return true;
	}

	function Log($message) {
		$this->debug_log .= $message . PHP_EOL;
	}

	function SetHeader($key, $value) {
		$this->headers[$key] = $value;
		return true;
	}

	function ClearHeader($key) {
		unset($this->headers[$key]);
		return true;
	}

	function SetNewAccessCallback($func) {
		$this->access_change_callback = $func;
		return true;
	}

	function make_sendable_body($data) {
		return json_encode(array('D' => $data));
	}

	function parse_cache_time($val) {
		$val = trim($val);

		$tag = substr($val, -1);
		$time = substr($val, 0, -1);

		// Assume seconds if no valid tag is passed.
		if (!preg_match('/[wdhm]/', $tag)) {
			return $val;
		}

		switch ($tag) {
			case 'w':
				$time = $time * 7;
			case 'd':
				$time = $time * 24;
			case 'h':
				$time = $time * 60;
			case 'm':
				$time = $time * 60;
		}

		return $time;
	}

	// source: http://www.php.net/manual/en/function.utf8-encode.php#83777
	function utf8_encode_mix($input, $encode_keys = false) {

		if (is_array($input)) {
			$result = array();
			foreach ($input as $k => $v) {
				$key = ($encode_keys) ? utf8_encode($k) : $k;
				$result[$key] = $this->utf8_encode_mix($v, $encode_keys);
			}
		}
		elseif (is_object($input)) {
			return $input;
		}
		else {
			$result = utf8_encode($input);
		}

		return $result;

	}

	function make_cache_key($request) {
		$string = $request['uri'] . '|' . serialize($request['headers']) . '|' . $request['cacheable_query_string'];
		return $this->cache_prefix . md5($string);
	}

	function return_all_results($response) {
		if ($response['success'] == true) {
			return $response['results'];
		}
		else {
			return false;
		}
	}

	function return_first_result($response) {
		if ($response['success'] == true) {
			if (count($response['results']) > 0) {
				return $response['results'][0];
			}
			else {
				return null;
			}
		}
		else {
			return false;
		}
	}

	/*
	 * API services
	 */

	function MakeAPICall($method, $service, $cache_time = 0, $params = array(), $post_data = null, $a_retry = false) {
	
		$this->ResetErrors();

		if ($this->transport == null) {
			$this->SetTransport(new SparkAPI_CurlTransport);
		}

		// parse format like "5m" into 300 seconds
		$seconds_to_cache = $this->parse_cache_time($cache_time);

		$request = array(
			'protocol' => ($this->force_https or $service == 'session') ? 'https' : 'http',
			'method' => $method,
			'uri' => '/' . $this->api_version . '/' . $service,
			'host' => $this->api_base,
			'headers' => $this->headers,
			'params' => $params,
			'post_data' => $post_data
		);

		// delegate to chosen authentication method for necessary changes to request
		$request = $this->sign_request($request);

		$served_from_cache = false;

		if ($this->cache and $method == "GET" and $a_retry != true and $seconds_to_cache > 0) {
			$response = $this->cache->get($this->make_cache_key($request));
			if ($response !== null) {
				$served_from_cache = true;
			}
		}

		if ($served_from_cache !== true) {
			$response = $this->transport->make_request($request);
		}

		$json = json_decode($response['body'], true);


		$return = array();
		$return['http_code'] = $response['http_code'];

		if (!is_array($json)) {
			// the response wasn't JSON as expected so bail out with the original, unparsed body
			$return['body'] = $response['body'];
			return $return;
		}

		if (array_key_exists('D', $json)) {
			if (array_key_exists('Code', $json['D'])) {
				$this->last_error_code = $json['D']['Code'];
				$return['api_code'] = $json['D']['Code'];
			}

			if (array_key_exists('Message', $json['D'])) {
				$this->last_error_mess = $json['D']['Message'];
				$return['api_message'] = $json['D']['Message'];
			}

			if (array_key_exists('Pagination', $json['D'])) {
				$this->last_count = $json['D']['Pagination']['TotalRows'];
				$this->page_size = $json['D']['Pagination']['PageSize'];
				$this->total_pages = $json['D']['Pagination']['TotalPages'];
				$this->current_page = $json['D']['Pagination']['CurrentPage'];
			} else {
				$this->last_count = null;
				$this->page_size = null;
				$this->total_pages = null;
				$this->current_page = null;
				$this->last_updated = null;
			}

			if (array_key_exists('LastUpdated', $json['D'])) {
				$this->last_updated = $json['D']['LastUpdated'];
				$return['last_updated'] = $json['D']['LastUpdated'];
			}


			if ($json['D']['Success'] == true) {
				$return['success'] = true;
        if (array_key_exists('Results', $json['D'])) {
          $return['results'] = $json['D']['Results'];
        }
        else {
          $return['results'] = array();
        }
			}
			else {
				$return['success'] = false;
			}
		}

		if (array_key_exists('access_token', $json)) {
			// looks like a successful OAuth grant response
			$return['success'] = true;
			$return['results'] = $json;
		}

		if (array_key_exists('error', $json)) {
			// looks like a failed OAuth grant response
			$return['success'] = false;
			$this->SetErrors($json['error'], $json['error_description']);
		}

		if ($return['success'] == true and $served_from_cache != true and $method == "GET" and $seconds_to_cache > 0) {
			if ($this->cache) {
				$this->cache->set($this->make_cache_key($request), $response, $seconds_to_cache);
			}
		}

		if ($return['success'] == false and $a_retry == false) {
			// see if this is a retry-type request
			if ($this->is_auth_request($request) == false and ($this->last_error_code == 1020 or $this->last_error_code == 1000)) {
				$retry = $this->ReAuthenticate();
				if (!$retry) {
					return $retry;
				}
				$return = $this->MakeAPICall($method, $service, $cache_time, $params, $post_data, true);
				return $return;
			}
		}

		return $return;

	}


	function HasBasicRole() {
		return false;
	}


	/*
	 * Listing services
	 */

	function GetListings($params = array()) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings", '10m', $params));
	}

	function GetListing($id, $params = array()) {
		return $this->return_first_result($this->MakeAPICall("GET", "listings/" . $id, '10m', $params));
	}

	function GetMyListings($params = array()) {
		return $this->return_all_results($this->MakeAPICall("GET", "my/listings", '10m', $params));
	}

	function GetOfficeListings($params = array()) {
		return $this->return_all_results($this->MakeAPICall("GET", "office/listings", '10m', $params));
	}

	function GetCompanyListings($params = array()) {
		return $this->return_all_results($this->MakeAPICall("GET", "company/listings", '10m', $params));
	}

	function GetListingPhotos($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/photos", '10m'));
	}

	function GetListingPhoto($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/photos/" . $sid, '10m'));
	}

	function GetListingVideos($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/videos", '10m'));
	}

	function GetListingVideo($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/videos/" . $sid, '10m'));
	}

	function GetListingOpenHouses($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/openhouses", '10m'));
	}

	function GetListingOpenHouse($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/openhouses/" . $sid, '10m'));
	}

	function GetListingVirtualTours($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/virtualtours", '10m'));
	}

	function GetListingVirtualTour($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/virtualtours/" . $sid, '10m'));
	}

	function GetListingDocuments($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/documents", '10m'));
	}

	function GetListingDocument($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/documents/" . $sid, '10m'));
	}

	function GetSharedListingNotes($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/shared/notes", '10m'));
	}


	/*
	 * Account services
	 */

	function GetAccounts($params = array()) {
		return $this->return_all_results($this->MakeAPICall("GET", "accounts", '1h', $params));
	}

	function GetAccount($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "accounts/" . $id, '1h'));
	}

	function GetAccountsByOffice($id, $params = array()) {
		return $this->return_all_results($this->MakeAPICall("GET", "accounts/by/office/" . $id, '1h', $params));
	}

	function GetMyAccount($params = array()) {
		return $this->return_first_result($this->MakeAPICall("GET", "my/account", '1h', $params));
	}

	function UpdateMyAccount($data) {
		return $this->return_all_results($this->MakeAPICall("PUT", "my/account", '1h', array(), $this->make_sendable_body($data)));
	}


	/*
	 * Contacts services
	 */

	function GetContacts($tags = null, $params = array()) {
		if (!is_null($tags)) {
			return $this->return_all_results($this->MakeAPICall("GET", "contacts/tags/" . rawurlencode($tags), 0, $params));
		}
		else {
			return $this->return_all_results($this->MakeAPICall("GET", "contacts", 0, $params));
		}
	}

	function AddContact($contact_data) {
		$data = array('Contacts' => array($contact_data));
		return $this->return_all_results($this->MakeAPICall("POST", "contacts", 0, array(), $this->make_sendable_body($data)));
	}

	function GetContact($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "contacts/" . $id));
	}

	function MyContact() {
		return $this->return_first_result($this->MakeAPICall("GET", "my/contact"));
	}


	/*
	 * Listing Carts services
	 */

	function GetListingCarts() {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts"));
	}

	function GetListingCartsWithListing($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts/for/listing/" . $id));
	}

	function GetPortalListingCarts() {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts/portal"));
	}

	function AddListingCart($name, $listings) {
		$data = array('ListingCarts' => array(array('Name' => $name, 'ListingIds' => $listings)));
		return $this->return_all_results($this->MakeAPICall("POST", "listingcarts", 0, array(), $this->make_sendable_body($data)));
	}

	function GetListingCart($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts/" . $id));
	}

	function AddListingsToCart($id, $listings) {
		$data = array('ListingIds' => $listings);
		return $this->return_all_results($this->MakeAPICall("POST", "listingcarts/" . $id, 0, array(), $this->make_sendable_body($data)));
	}

	function UpdateListingsInCart($id, $listings) {
		$data = array('ListingIds' => $listings);
		return $this->return_all_results($this->MakeAPICall("PUT", "listingcarts/" . $id, 0, array(), $this->make_sendable_body($data)));
	}

	function DeleteListingCart($id) {
		return $this->return_all_results($this->MakeAPICall("DELETE", "listingcarts/" . $id));
	}

	function DeleteListingsFromCart($id, $listings) {
		return $this->return_all_results($this->MakeAPICall("DELETE", "listingcarts/" . $id . "/listings/" . $listings));
	}


	/*
	 * Market Statistics services
	 */

	function GetMarketStats($type, $options = "", $property_type = "", $location_name = "", $location_value = "") {

		$args = array();

		if (!empty($options)) {
			$args['Options'] = $options;
		}

		if (!empty($property_type)) {
			$args['PropertyTypeCode'] = $property_type;
		}

		if (!empty($location_name)) {
			$args['LocationField'] = $location_name;
			$args['LocationValue'] = $location_value;
		}

		return $this->return_first_result($this->MakeAPICall("GET", "marketstatistics/" . $type, '48h', $args));
	}


	/*
	 * Messaging services
	 */

	function AddMessage($data) {
		$data = array('Messages' => array($data));
		return $this->return_all_results($this->MakeAPICall("POST", "messages", 0, array(), $this->make_sendable_body($data)));
	}

	/*
	 * Saved Searches services
	 */

	function GetSavedSearches() {
		return $this->return_all_results($this->MakeAPICall("GET", "savedsearches", '30m'));
	}

	function GetSavedSearch($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "savedsearches/" . $id, '30m'));
	}


	/*
	 * IDX Links services
	 */

	function GetIDXLinks($params = array()) {
		return $this->return_all_results($this->MakeAPICall("GET", "idxlinks", '24h', $params));
	}

	function GetIDXLink($id) {
		return $this->return_first_result($this->MakeAPICall("GET", "idxlinks/" . $id, '24h'));
	}

	function GetTransformedIDXLink($link, $args = array()) {
		$response = $this->return_first_result($this->MakeAPICall("GET", "redirect/idxlink/" . $link, '30m', $args));

		if ($response != null) {
			return $response['Uri'];
		}

		return $response;
	}


	/*
	 * Preferences services
	 */

	function GetPreferences() {
		$response = $this->return_all_results($this->MakeAPICall("GET", "connect/prefs", '24h'));

		$records = array();
		foreach ($response as $pref) {
			$records[$pref['Name']] = $pref['Value'];
		}

		return $records;
	}


	/*
	 * Property Types services
	 */

	function GetPropertyTypes() {
		$response = $this->MakeAPICall("GET", "propertytypes", '24h');

		if ($response['success'] == true) {
			$records = array();
			foreach ($response['results'] as $res) {
				$records[$res['MlsCode']] = $res['MlsName'];
			}

			return $records;
		}
		else {
			return false;
		}
	}


	/*
	 * Standard Fields services
	 */

	function GetStandardFields() {
		return $this->return_all_results($this->MakeAPICall("GET", "standardfields", '24h'));
	}

	function GetStandardFieldList($field) {
		$data = $this->return_first_result($this->MakeAPICall("GET", "standardfields/" . $field, '24h'));
		if ($data) {
			return $data[$field]['FieldList'];
		}
		else {
			return $data;
		}
	}


	/*
	 * Custom Fields services
	 */

	function GetCustomFields() {
		return $this->return_all_results($this->MakeAPICall("GET", "customfields", '24h'));
	}

	function GetCustomFieldList($field) {
		$data = $this->return_first_result($this->MakeAPICall("GET", "customfields/" . rawurlencode($field), '24h'));
		if ($data && array_key_exists('FieldList', $data[$field]) ) {
			return $data[$field]['FieldList'];
		}
		else {
			return array();
		}
	}

	/*
	 * System Info services
	 */

	function GetSystemInfo() {
		return $this->return_first_result($this->MakeAPICall("GET", "system", '24h'));
	}
	
	/*
	 * Error services
	 */
	function SetErrors($code, $message) {
		$this->last_error_code = $code;
		$this->last_error_mess = $message;
	}

	function ResetErrors() {
		$this->last_error_code = false;
		$this->last_error_mess = false;
	}

	function GetErrors() {
		if ($this->last_error_code || $this->last_error_mess){
			return $this->last_error_code.' - '.$this->last_error_mess;
		} else {
			return false;
		}
	}	

	/**
	 * Performs a GET request to Spark API.  Wraps MakeAPIRequest.
	 * @param  $options  An array with the following potential attributes:
	 *                     'cache_time' => The time, either in seconds or in the format
	 *                                     specified by parse_cache_time, to cache the response.
	 *                     'parameters' => The array of request parameters to send along with
	 *                                     the request.
	 * @return array An array from the parsed JSON response.  This will typically be in the format:
	 *                    'success' => true if the response was successful
	 *                    'results' => An array of the "Results" attribute
	 *               If success is false, consult $this->GetErrors().
	 */
	function get($service, $options = array()) {
		return $this->wrapped_api_call('GET', $service, $options);
	}

	/**
	 * Performs a POST request to Spark API.  Wraps MakeAPIRequest.
	 * @param  $options  An array with the following potential attributes:
	 *                     'cache_time' => The time, either in seconds or in the format
	 *                                     specified by parse_cache_time, to cache the response.
	 *                     'parameters' => The array of request parameters to send along with
	 *                                     the request.
	 *                     'data'       => The POST data, as an array that will be later translated
	 *                                     to JSON.  Ignore the "D" attribute -- we will wrap the data
	 *                                     with the "D" attribute for you.
	 * @return array An array from the parsed JSON response.  This will typically be in the format:
	 *                    'success' => true if the response was successful
	 *                    'results' => An array of the "Results" attribute
	 *               If success is false, consult $this->GetErrors().
	 */
	function post($service, $options = array()) {
		return $this->wrapped_api_call('POST', $service, $options);
	}

	/**
	 * Performs a PUT request to Spark API.  Wraps MakeAPIRequest.
	 * @param  $options  An array with the following potential attributes:
	 *                     'cache_time' => The time, either in seconds or in the format
	 *                                     specified by parse_cache_time, to cache the response.
	 *                     'parameters' => The array of request parameters to send along with
	 *                                     the request.
	 *                     'data'       => The PUT data, as an array that will be later translated
	 *                                     to JSON.  Ignore the "D" attribute -- we will wrap the data
	 *                                     with the "D" attribute for you.
	 * @return array An array from the parsed JSON response.  This will typically be in the format:
	 *                    'success' => true if the response was successful
	 *                    'results' => An array of the "Results" attribute
	 *               If success is false, consult $this->GetErrors().
	 */
	function put($service, $options = array()) {
		return $this->wrapped_api_call('PUT', $service, $options);
	}

	/**
	 * Performs a DELETE request to Spark API.  Wraps MakeAPIRequest.
	 * @param  $options  An array with the following potential attributes:
	 *                     'cache_time' => The time, either in seconds or in the format
	 *                                     specified by parse_cache_time, to cache the response.
	 *                     'parameters' => The array of request parameters to send along with
	 *                                     the request.
	 * @return array An array from the parsed JSON response.  This will typically be in the format:
	 *                    'success' => true if the response was successful
	 *                    'results' => An array of the "Results" attribute
	 *               If success is false, consult $this->GetErrors().
	 */
	function delete($service, $options = array()) {
		return $this->wrapped_api_call('DELETE', $service, $options);
	}

	function GetSparkBarToken($access_token) {

		$this->ResetErrors();

		if ($this->transport == null) {
			$this->SetTransport(new SparkAPI_CurlTransport);
		}

		$spark_bar_headers = $this->headers;
		unset($spark_bar_headers["Content-Type"]);

		$request = array(
			'protocol' => 'https',
			'method' => 'POST',
			'uri' => '/appbar/authorize',
			'host' => $this->platform_base,
			'headers' => $spark_bar_headers,
			'post_data' => array("access_token" => $access_token)
		);

		$response = $this->transport->make_request($request);
		$json = json_decode($response['body'], true);

		if (!is_array($json)) {
			return $this->SetErrors(null, "An unknown error occurred when retrieving your sparkbar token.");
		}

		$token = null;
		if (array_key_exists('success', $json)) {
			if ($json['success'] == True) {
				$token = $json["token"];
			}
			else {
				return $this->SetErrors(null, $json['error']);
			}
		}

		return $token;
	}

	protected function extract_from_request_options($key, $options=array(), $default) {
		if (array_key_exists($key, $options)) {
			return $options[$key];
		}
		else {
			return $default;
		}
	}

	protected function wrapped_api_call($method, $service, $options) {
		$cache_time = $this->extract_from_request_options('cache_time', $options, 0);
		$params     = $this->extract_from_request_options('parameters', $options, array());
		$post_data  = $this->extract_from_request_options('data',       $options, null);
		$a_retry    = $this->extract_from_request_options('retry',      $options, false);

		if ($post_data) {
			$post_data = $this->make_sendable_body($post_data);
		}
		$service = rawurlencode($service);
		$service = trim($service, "/ ");

		return $this->MakeAPICall($method, $service, $cache_time, $params, $post_data, $a_retry);
	}

}
