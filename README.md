[![Build Status](https://travis-ci.org/sparkapi/sparkapi4p2.png?branch=master)](https://travis-ci.org/sparkapi/sparkapi4p2)

Spark API - version 2
=====================
A PHP wrapper for the Spark REST API.  This version has enough differences from version 1 that upgrading will
require changes to existing code.


Documentation
-------------
For additional information on the PHP client, [visit the wiki](https://github.com/sparkapi/sparkapi4p2/wiki).

For full information on the API, see http://sparkplatform.com/docs

Installation
-------------
`composer require sparkapi/sparkapi:dev-master`

Usage Examples 
------------------------
    require_once("vendor/autoload.php");

    // connect using Access Token Authentication (additional authentication methods available in the wiki)
    $api = new SparkAPI_Bearer("your_access_token_here");

    // identify your application (optional)
    $api->SetApplicationName("MyPHPApplication/1.0");

    // get your listings
    $results = $api->GetMyListings();
    
    foreach ($results as $result) {
        // standard fields expected in the resource payload differ by MLS and role
        echo $result['StandardFields']['ListingKey'];
        // -or- 
        print_r($result);
    }

    /*
	 * Alternatively, if the appropriate helper method doesn't exist,
	 * try our "get", "post", "put", or "delete" methods from Core.php
	 * directly to the endpoint you want to use. 
	 */
	$result = $api->get("my/account");
	
	print_r($result);

    // see the included examples.php for more complete usage

Error Codes
---------------------
A list of all API error codes can be found [here](http://sparkplatform.com/docs/supporting_documentation/error_codes).
