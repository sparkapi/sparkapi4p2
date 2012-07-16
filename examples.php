<?php

header('Content-Type: text/html; charset=UTF-8');

// include the SparkAPI core which autoloads other classes as necessary
require_once("lib/Core.php");


/*
 * authenticate with the API
 * Changed in version 2.0
 *
 * The newest version of the PHP API client (version 2.0) allows you to select which authentication method
 * you'd like to use against the API.  Version 1.0 was limited to the Spark API authentication method and is
 * now done using:
 *
 *      $api = new SparkAPI_APIAuth("api_key_goes_here", "api_secret_goes_here");
 *
 * With version 2.0, you can now authenticate using OAuth2 support.  For more details on OAuth2 with the Spark API,
 * see http://sparkplatform.com/docs/authentication/oauth2_authentication
 *
 *      $api = new SparkAPI_OAuth($client_id, $client_secret, $application_uri);
 *
 * To issue a Grant request with the "code" value provided by the API:
 *
 *      $result = $api->Grant($code_value);
 *
 * A successful response will populate 2 new variables:
 *
 *      $api->oauth_access_token
 *      $api->oauth_refresh_token
 *
 * These values can be saved and re-used in future requests using:
 *
 *      $api->SetAccessToken($previous_access_token);
 *      $api->SetRefreshToken($previous_refresh_token);
 *
 * Also, for convenience, if the access token has expired and the refresh token is used to automatically have a new access
 * token generated, a hook is available to notify you of the new tokens:
 *
 *      $api->SetNewAccessCallback('new_token_given');
 *
 * which executes a function called "new_token_given" with 2 arguments:
 *
 *      * the type of grant which resulted in new tokens.  "authorization_code" or "refresh_token"
 *      * the values (in array format).  "access_token", "refresh_token" and "expires_in" are the 3 array keys
 *
 */

$api = new SparkAPI_APIAuth("api_key_goes_here", "api_secret_goes_here");

// identify your application (optional)
$api->SetApplicationName("PHP-API-Code-Examples/1.0");

// enable developer mode.  this points you to a sandbox for development use
$api->SetDeveloperMode(true);


/*
 * enable built-in caching system
 * New in version 2.0
 *
 * The newest version of the PHP API client (version 2.0) contains a system for enabling a
 * built-in cache.  The following options currently exist:
 *
 *    * Memcache (Uses http://pecl.php.net/package/memcache)
 *    * Memcached (Uses http://pecl.php.net/package/memcached)
 *    * WordPress (for use within plugins or themes)
 *    * MySQLi for storing the cache in a MySQL database
 *
 * To enable a particular caching system, you must create an instance of the desired object and pass it to
 * the API core.
 *
 *
 * To enable Memcache or Memcached cache support, the host and port (both optional) can be given:
 *
 *      $api->SetCache( new SparkAPI_MemcacheCache() ); // defaults to localhost and port 11211
 *  or:
 *      $api->SetCache( new SparkAPI_MemcachedCache('remotehost', 12345) ); // overrides both defaults
 *
 * depending on the Memcached-compliant driver you choose.
 *
 *
 * To enable WordPress caching, no arguments are required: this method uses the set_transient() and get_transient()
 * functions created by WordPress which can be extended by other WP plugins for additional (or modified) functionality.
 *
 *      $api->SetCache( new SparkAPI_WordPressCache() );
 *
 *
 * To enable database caching via the MySQLi extension, you can either pass connection details to the class:
 *
 *      $api->SetCache( new SparkAPI_MySQLiCache($hostname, $database, $username, $password, $table_name));
 *
 * or you can re-use an existing MySQLi connection by passing the object:
 *
 *      $api->SetCache( new SparkAPI_MySQLiCache($my_mysqli_object) );
 *
 * By default, a $table_name of "api_cache" is assumed if none is given.  The structure for that table is:
 *
 *    CREATE TABLE api_cache (
 *       cache_key VARCHAR(125),
 *       cache_value TEXT,
 *       expiration INT(10),
 *       PRIMARY KEY(cache_key)
 *    )
 *
 */


// authenticate
$result = $api->Authenticate();
if ($result === false) {
    echo "API Error Code: {$api->last_error_code}<br>\n";
    echo "API Error Message: {$api->last_error_mess}<br>\n";
    exit;
}

/*
 * request some basic account and system information
 */
$result = $api->GetSystemInfo();
// http://sparkplatform.com/docs/api_services/system_info
print_r($result);

$result = $api->GetPropertyTypes();
// http://sparkplatform.com/docs/api_services/property_types
print_r($result);

$result = $api->GetStandardFields();
// http://sparkplatform.com/docs/api_services/standard_fields
print_r($result);

$result = $api->GetMyAccount();
// http://sparkplatform.com/docs/api_services/my_account
print_r($result);


/*
 * different requests for listings based on context
 */

$result = $api->GetMyListings();
// http://sparkplatform.com/docs/api_services/listings
print_r($result);

$result = $api->GetOfficeListings();
// http://sparkplatform.com/docs/api_services/listings
print_r($result);

$result = $api->GetCompanyListings();
// http://sparkplatform.com/docs/api_services/listings
print_r($result);

/*
 * request for listings with some parameters.  the above listing requests this argument and most of the options within
 */
$result = $api->GetListings(
	array(
		'_pagination' => 1,
		'_limit' => 3,
		'_page' => 2,
		'_filter' => "PropertyType Eq 'A'",
		'_expand' => 'PrimaryPhoto'
	)
);
// http://sparkplatform.com/docs/api_services/listings
print_r($result);

/*
 * with a particular listing Id known, several additional API calls are available
 */

$id = "20100912153422758914000000"; // this comes from the Id value in a listing response

$result = $api->GetListingPhotos($id);
// http://sparkplatform.com/docs/api_services/listings/photos
$result = $api->GetListingDocuments($id);
// http://sparkplatform.com/docs/api_services/listings/listing_documents
$result = $api->GetListingOpenHouses($id);
// http://sparkplatform.com/docs/api_services/listings/open_houses
$result = $api->GetListingVideos($id);
// http://sparkplatform.com/docs/api_services/listings/videos
$result = $api->GetListingVirtualTours($id);
// http://sparkplatform.com/docs/api_services/listings/virtual_tours


/*
 * with a particular object Id known, you can request additional information about that one item
 */

$photo_id = "20080917142739989238000000";

$result = $api->GetListingPhoto($id, $photo_id);
// http://sparkplatform.com/docs/api_services/listings/photos


/*
 * contact management
 * http://sparkplatform.com/docs/api_services/contacts
 */

$result = $api->GetContacts();

$new_contact = array(
	"DisplayName" => "Example Contact",
	"PrimaryEmail" => "apiexample@sparkplatform.com",
	"PrimaryPhoneNumber" => "888-123-4567",
	"HomeStreetAddress" => "123 S. Main St",
	"HomeLocality" => "Fargo",
	"HomeRegion" => "ND",
	"HomePostalCode" => "58104",
	"Tag" => "Example Group"
);

// $result = $api->AddContact($new_contact); // creates a new contact

$result = $api->GetContact("20090816141725963238000000"); // get a contact by their Id
