[![Build Status](https://travis-ci.org/sparkapi/sparkapi4p2.png?branch=master)](https://travis-ci.org/sparkapi/sparkapi4p2)

Spark API - version 2
=====================
A PHP wrapper for the Spark REST API.  This version has enough differences from version 1 that upgrading will
require changes to existing code.


Documentation
-------------
For additional information on the PHP client, [visit the wiki](https://github.com/sparkapi/sparkapi4p2/wiki).

For full information on the API, see http://sparkplatform.com/docs


Usage Examples 
------------------------
    // include the Spark core which autoloads other classes as necessary
    require_once("lib/Core.php");

    // connect using Access Token Authentication (additional authentication methods available in the wiki)
    $api = new SparkAPI_Bearer("your_access_token_here");

    // identify your application (optional)
    $api->SetApplicationName("MyPHPApplication/1.0");


    // get your listings
    $result = $api->GetMyListings();

	/*
		Alternatively, if you cannot find the appropriate helper method,
		try our "get", "post", "put", or "delete" methods from Core.php. 
	*/
	$result = $api->get("my/listings");

    // see the included examples.php for more complete usage

Error Codes
---------------------
A list of all API error codes can be found [here](http://sparkplatform.com/docs/supporting_documentation/error_codes).
