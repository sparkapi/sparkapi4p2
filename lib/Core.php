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
    We'll no longer advertise a separate domain for development,
    but will keep the logic around for backwards compat.
   */
	const DEVELOPERS_API_BASE = "sparkapi.com";
	
	public $api_client_version = '2.0';

	public $api_base = self::DEFAULT_API_BASE;
	public $platform_base = self::DEFAULT_PLATFORM_BASE;
	public $api_version = "v1";
	
	protected $developer_mode = false;
	protected $force_https = false;
	protected $transport = null;
	protected $cache = null;
	protected $cache_prefix = "SparkAPI_";

	protected $headers = [];

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
	public function __construct() {
		$this->SetHeader("Content-Type", "application/json");
		$this->SetHeader('User-Agent', 'Spark API PHP Client/' . $this->api_client_version);
	}

	static function autoload($class_name) {
		if (preg_match('/^SparkAPI/', $class_name)) {
			$file_name = preg_replace('/^SparkAPI\_/', '', $class_name);
			include_once(dirname(realpath(__FILE__)) . '/' . $file_name . '.php');
		}
	}

	public function SetApplicationName($name) {
		$this->SetHeader('X-SparkApi-User-Agent', str_replace(array("\r", "\r\n", "\n"), '', trim($name)));
	}

	public function SetDeveloperMode($enable = false) {
		$this->developer_mode = $enable;
		if ($enable) {
			$this->api_base = self::DEVELOPERS_API_BASE;
		}
		else {
			$this->api_base = self::DEFAULT_API_BASE;
		}
		return $enable;
	}

	public function SetTransport($transport) {
		if (is_object($transport)) {
			$this->transport = $transport;
		}
		else {
			throw new Exception("SetTransport() called but value isn't a valid transport object");
		}
	}

	public function SetCache($cache) {
		if (is_object($cache)) {
			$this->cache = $cache;
		}
		else {
			throw new Exception("SetCache() called but value isn't a valid cache object");
		}
	}

	public function SetCachePrefix($prefix) {
		$this->cache_prefix = $prefix;
		return true;
	}
	
	public function SetHeader($key, $value) {
		$this->headers[$key] = $value;
		return true;
	}

	public function ClearHeader($key) {
		unset($this->headers[$key]);
		return true;
	}

	public function SetNewAccessCallback($func) {
		$this->access_change_callback = $func;
		return true;
	}

	public function make_sendable_body($data) {
		return json_encode(array('D' => $data));
	}

	public function parse_cache_time($val) {
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
	public function utf8_encode_mix($input, $encode_keys = false) {

		if (is_array($input)) {
			$result = [];
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

	public function make_cache_key($request) {
		$string = $request['uri'] . '|' . serialize($request['headers']) . '|' . $request['cacheable_query_string'];
		return $this->cache_prefix . md5($string);
	}

	public function return_all_results($response) {
		if ($response['success'] == true) {
			return $response['results'];
		}
		else {
			return false;
		}
	}

	public function return_first_result($response) {
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
	public function MakeAPICall($method, $service, $cache_time = 0, $params = [], $post_data = null, $a_retry = false) {
	
		$this->ResetErrors();

		if ($this->transport == null) {
			$this->SetTransport(new SparkAPI_CurlTransport);
		}

		// parse format like "5m" into 300 seconds
		$seconds_to_cache = $this->parse_cache_time($cache_time);
		
		// check if it's a random orderby
		$random = (array_key_exists('_orderby', $params) && $params['_orderby'] == 'Random') ? true : false;

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

		if ($this->cache and $method == "GET" and $a_retry != true and $seconds_to_cache > 0 and !$random) {
			$response = $this->cache->get($this->make_cache_key($request));
			if ($response !== null) {
				$served_from_cache = true;
			}
		}

		if ($served_from_cache !== true) {
			$response = $this->transport->make_request($request);
		}

		$json = json_decode($response['body'], true);

		$return = [];
		$return['http_code'] = $response['http_code'];

		if (!is_array($json)) {
			// the response wasn't JSON as expected so bail out with the original, unparsed body
			$this->SetErrors(null, "Invalid response body format - Expected JSON \n" . $response['body']);
			
			$this->Log($this->GetErrors());
			$this->ResetErrors();
			
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
			}

			if (array_key_exists('LastUpdated', $json['D'])) {
				$this->last_updated = $json['D']['LastUpdated'];
				$return['last_updated'] = $json['D']['LastUpdated'];
			} else {
				$this->last_updated = null;
			}
			if (array_key_exists('SparkQLErrors', $json['D'])) {
				$return['sparkqlerrors'] = $json['D']['SparkQLErrors'];	
			}


			if ($json['D']['Success'] == true) {
				$return['success'] = true;
        if (array_key_exists('Results', $json['D'])) {
          $return['results'] = $json['D']['Results'];
        }
        else {
          $return['results'] = [];
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
			if ($this->cache and !$random) {
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

		$this->Log($this->GetErrors());
		$this->ResetErrors();
		
		return $return;

	}

	public function HasBasicRole() {
		return false;
	}

	/*
	 * Listing services
	 */
	public function GetListings($params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings", '10m', $params));
	}

	public function GetListing($id, $params = []) {
		return $this->return_first_result($this->MakeAPICall("GET", "listings/" . $id, '10m', $params));
	}

	public function GetMyListings($params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "my/listings", '10m', $params));
	}

	public function GetOfficeListings($params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "office/listings", '10m', $params));
	}

	public function GetCompanyListings($params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "company/listings", '10m', $params));
	}

	public function GetListingPhotos($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/photos", '10m'));
	}

	public function GetListingPhoto($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/photos/" . $sid, '10m'));
	}

	public function GetListingVideos($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/videos", '10m'));
	}

	public function GetListingVideo($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/videos/" . $sid, '10m'));
	}

	public function GetListingOpenHouses($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/openhouses", '10m'));
	}

	public function GetListingOpenHouse($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/openhouses/" . $sid, '10m'));
	}

	public function GetListingVirtualTours($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/virtualtours", '10m'));
	}

	public function GetListingVirtualTour($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/virtualtours/" . $sid, '10m'));
	}

	public function GetListingDocuments($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/documents", '10m'));
	}

	public function GetListingDocument($id, $sid) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/documents/" . $sid, '10m'));
	}

	public function GetSharedListingNotes($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/" . $id . "/shared/notes", '10m'));
	}
	
	public function GetListingsClustered($params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "listings/clusters", '10m', $params));
	}

	/*
	 * Account services
	 */
	public function GetAccounts($params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "accounts", '1h', $params));
	}

	public function GetAccount($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "accounts/" . $id, '1h'));
	}

	public function GetAccountsByOffice($id, $params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "accounts/by/office/" . $id, '1h', $params));
	}

	public function GetMyAccount($params = []) {
		return $this->return_first_result($this->MakeAPICall("GET", "my/account", '1h', $params));
	}

	public function UpdateMyAccount($data) {
		return $this->return_all_results($this->MakeAPICall("PUT", "my/account", '1h', [], $this->make_sendable_body($data)));
	}

	/*
	 * Contacts services
	 */
	public function GetContacts($tags = null, $params = []) {
		if (!is_null($tags)) {
			return $this->return_all_results($this->MakeAPICall("GET", "contacts/tags/" . rawurlencode($tags), 0, $params));
		}
		else {
			return $this->return_all_results($this->MakeAPICall("GET", "contacts", 0, $params));
		}
	}

	public function AddContact($contact_data) {
		$data = array('Contacts' => array($contact_data));
		return $this->return_all_results($this->MakeAPICall("POST", "contacts", 0, [], $this->make_sendable_body($data)));
	}

	public function GetContact($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "contacts/" . $id));
	}

	public function MyContact() {
		return $this->return_first_result($this->MakeAPICall("GET", "my/contact"));
	}

	/*
	 * Listing Carts services
	 */
	public function GetListingCarts() {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts"));
	}

	public function GetListingCartsWithListing($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts/for/" . $id));
	}

	public function GetPortalListingCarts() {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts/portal"));
	}

	public function AddListingCart($name, $listings) {
		$data = array('ListingCarts' => array(array('Name' => $name, 'ListingIds' => $listings)));
		return $this->return_all_results($this->MakeAPICall("POST", "listingcarts", 0, [], $this->make_sendable_body($data)));
	}

	public function GetListingCart($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "listingcarts/" . $id));
	}

	public function AddListingsToCart($id, $listings) {
		$data = array('ListingIds' => $listings);
		return $this->return_all_results($this->MakeAPICall("POST", "listingcarts/" . $id, 0, [], $this->make_sendable_body($data)));
	}

	public function UpdateListingsInCart($id, $listings) {
		$data = array('ListingIds' => $listings);
		return $this->return_all_results($this->MakeAPICall("PUT", "listingcarts/" . $id, 0, [], $this->make_sendable_body($data)));
	}

	public function DeleteListingCart($id) {
		return $this->return_all_results($this->MakeAPICall("DELETE", "listingcarts/" . $id));
	}

	public function DeleteListingsFromCart($id, $listings) {
		return $this->return_all_results($this->MakeAPICall("DELETE", "listingcarts/" . $id . "/listings/" . $listings));
	}
	
	public function InitiateContactPortal($contact_id){
		return $this->MakeAPICall("POST", "contacts/".$contact_id."/portal");
	}

	/*
	 * Market Statistics services
	 */
	public function GetMarketStats($type, $options = "", $property_type = "", $location_name = "", $location_value = "") {

		$args = [];

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
	public function AddMessage($data) {
		$data = array('Messages' => array($data));
		return $this->return_all_results($this->MakeAPICall("POST", "messages", 0, [], $this->make_sendable_body($data)));
	}

	/*
	 * Saved Searches services
	 */
	public function GetSavedSearches() {
		return $this->return_all_results($this->MakeAPICall("GET", "savedsearches", '30m'));
	}

	public function GetSavedSearch($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "savedsearches/" . $id, '30m'));
	}
	
	public function GetProvidedSavedSearches() {
		return $this->return_all_results($this->MakeAPICall("GET", "provided/savedsearches", '30m'));
	}

	public function GetProvidedSavedSearch($id) {
		return $this->return_all_results($this->MakeAPICall("GET", "provided/savedsearches/" . $id, '30m'));
	}

	/*
	 * IDX Links services
	 */
	public function GetIDXLinks($params = []) {
		return $this->return_all_results($this->MakeAPICall("GET", "idxlinks", '24h', $params));
	}

	public function GetIDXLink($id) {
		return $this->return_first_result($this->MakeAPICall("GET", "idxlinks/" . $id, '24h'));
	}

	public function GetTransformedIDXLink($link, $args = []) {
		$response = $this->return_first_result($this->MakeAPICall("GET", "redirect/idxlink/" . $link, '30m', $args));

		if ($response != null) {
			return $response['Uri'];
		}

		return $response;
	}


	/*
	 * Preferences services
	 */
	public function GetPreferences() {
		$response = $this->return_all_results($this->MakeAPICall("GET", "connect/prefs", '24h'));

		$records = [];
		foreach ($response as $pref) {
			$records[$pref['Name']] = $pref['Value'];
		}

		return $records;
	}


	/*
	 * Property Types services
	 */
	public function GetPropertyTypes() {
		$response = $this->MakeAPICall("GET", "propertytypes", '24h');

		if ($response['success'] == true) {
			$records = [];
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
	public function GetStandardFields() {
		return $this->return_all_results($this->MakeAPICall("GET", "standardfields", '24h'));
	}

	public function GetStandardFieldList($field) {
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
	public function GetCustomFields() {
		return $this->return_all_results($this->MakeAPICall("GET", "customfields", '24h'));
	}

	public function GetCustomFieldList($field) {
		$data = $this->return_first_result($this->MakeAPICall("GET", "customfields/" . rawurlencode($field), '24h'));
		if ($data && array_key_exists('FieldList', $data[$field]) ) {
			return $data[$field]['FieldList'];
		}
		else {
			return [];
		}
	}

	/*
	 * System Info services
	 */
	public function GetSystemInfo() {
		return $this->return_first_result($this->MakeAPICall("GET", "system", '24h'));
	}
	
	/*
	 * Error services
	 */
	public function SetErrors($code, $message) {
		$this->last_error_code = $code;
		$this->last_error_mess = $message;
	}

	public function ResetErrors() {
		$this->last_error_code = false;
		$this->last_error_mess = false;
	}

	public function GetErrors() {
		if ($this->last_error_code || $this->last_error_mess){
			return $this->last_error_code.' - '.$this->last_error_mess;
		} else {
			return false;
		}
	}	
	
	public function Log($message) {
		if (ini_get('log_errors') == true && $message){
			error_log("Spark Api Client/" . $this->api_client_version . ' - ' . $message, 0);
			
			return true;
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
	public function get($service, $options = []) {
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
	public function post($service, $options = []) {
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
	public function put($service, $options = []) {
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
	public function delete($service, $options = []) {
		return $this->wrapped_api_call('DELETE', $service, $options);
	}

	protected function extract_from_request_options($key, $options = [], $default) {
		if (array_key_exists($key, $options)) {
			return $options[$key];
		}
		else {
			return $default;
		}
	}

	protected function wrapped_api_call($method, $service, $options) {
		$cache_time = $this->extract_from_request_options('cache_time', $options, 0);
		$params     = $this->extract_from_request_options('parameters', $options, []);
		$post_data  = $this->extract_from_request_options('data',       $options, null);
		$a_retry    = $this->extract_from_request_options('retry',      $options, false);

		if ($post_data) {
			$post_data = $this->make_sendable_body($post_data);
		}
		$service = trim($service, "/ ");

		return $this->MakeAPICall($method, $service, $cache_time, $params, $post_data, $a_retry);
	}

}
