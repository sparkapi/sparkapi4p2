<?php

class SparkAPI_OIDCAuth extends SparkAPI_Core implements SparkAPI_AuthInterface
{
    const SPARK_PROVIDER = 'https://sparkplatform.com';
    public $oauth_key;
    public $oauth_secret;
    public $redirect_uri;
    public $auth_mode;
    public $access_token;
    public $last_token;
    public $refresh_token;

    public function __construct($oauth_key, $oauth_secret, $redirect_uri)
    {
        $this->auth_mode = 'OIDC';
        $this->oauth_key = $oauth_key;
        $this->redirect_uri = $redirect_uri;
        $this->oauth_secret = $oauth_secret;
        $this->forceHttps();

        parent::__construct();
    }

    public function Authenticate()
    {
        $oidc = new OpenIDConnectClient(SparkAPI_OIDCAuth::SPARK_PROVIDER,
            $this->oauth_key,
            $this->oauth_secret
        );

        $oidc->setRedirectURL($this->redirect_uri);
        $oidc->addAuthParam([
            'X-SparkApi-User-Agent' => 'SparkOIDC',
            'User-Agent' => 'SparkOIDC'
        ]);

        try {
            $oidc->authenticate();
            $this->SetAccessToken($oidc->getAccessToken());
            $this->SetRefreshToken($oidc->getRefreshToken());
        } catch (OpenIDConnectClientException $e) {
            var_dump($e->getMessage());
            die();
        }
    }

    function sign_request($request) {
        $this->SetHeader('Authorization', 'Bearer '. $this->last_token);

        // reload headers into request
        $request['headers'] = $this->headers;
        $request['query_string'] = http_build_query($request['params']);
        $request['cacheable_query_string'] = $request['query_string'];

        return $request;

    }

    function SetAccessToken($token) {
        $this->access_token = $token;
        $this->last_token = $token;
    }

    function SetRefreshToken($token) {
        $this->refresh_token = $token;
    }

    function GetAccessToken() {
        return $this->last_token;
    }

    function GetRefreshToken() {
        return $this->refresh_token;
    }

    function forceHttps()
    {
        $this->force_https = true;
    }
}