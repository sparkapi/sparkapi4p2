<?php

header('Content-Type: text/html; charset=UTF-8');

require_once ("lib/Core.php");
require_once ('vendor/autoload.php');

$oauth_key = '';
$oauth_secret = '';
/*
 * set the redirect URL up with FlexMLS API Support-- should be https://yourdomain.com/index.php if you don't change
 * the URL structure from this example
 */
$oauth_redirect_url = '';

$api = new SparkAPI_OIDCAuth($oauth_key, $oauth_secret, $oauth_redirect_url);

// identify your application (optional)
$api->SetApplicationName("PHP-API-Code-Examples/1.0");

/*
 * Authenticate the application against the Spark API-- this call handles the whole flow
 */
$api->Authenticate();

/*
 * If we have a token set, we're ready to make requests-- in this example we're working from the same page as we'd
 * normally be using as a callback / auth page, which would store token / refresh token in a database. Since this is
 * just a demo, we explicitly check for the token before making a request.
 */
if ($api->GetAccessToken()) {
    $listings = $api->getListings();
    var_dump($listings);
}