<?php

header('Content-Type: text/html; charset=UTF-8');

require_once ("lib/Core.php");
require_once ('vendor/autoload.php');

$api = new SparkAPI_OIDCAuth("OAuth_key", "OAuth_secret", "OAuth_redirect_url");

// identify your application (optional)
$api->SetApplicationName("PHP-API-Code-Examples/1.0");
// authenticate
$api->Authenticate();

// if we have a token, we will be ready to make requests
$token = $api->GetAccessToken() ?: null;

if (isset($token)) {
    $listings = $api->getListings();
    var_dump($listings);
}