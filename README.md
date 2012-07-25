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

    // connect using Spark API authentication
    $api = new SparkAPI_APIAuth("api_key_goes_here", "api_secret_goes_here");

    // identify your application (optional)
    $api->SetApplicationName("MyPHPApplication/1.0");

    // authenticate
    $result = $api->Authenticate();
    if ($result === false) {
        echo "API Error Code: {$api->last_error_code}<br>\n";
        echo "API Error Message: {$api->last_error_mess}<br>\n";
        exit;
    }

    // get your listings
    $result = $api->GetMyListings();

    // see the included examples.php for more complete usage

Error Codes
---------------------
A list of all API error codes can be found [here](http://sparkplatform.com/docs/supporting_documentation/error_codes).
