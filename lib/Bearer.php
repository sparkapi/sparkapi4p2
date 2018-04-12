<?php

class SparkAPI_Bearer extends SparkAPI_Core implements SparkAPI_AuthInterface
{
    public $access_token;

    public function __construct($access_token)
    {
        $this->access_token = $access_token;
        $this->force_https = true;
        parent::__construct();
    }

    function sign_request($request) {
        $this->SetHeader('Authorization', 'Bearer '. $this->access_token);
        $this->SetHeader('X-SparkApi-User-Agent', 'Thinkery');

        // reload headers into request
        $request['headers'] = $this->headers;
        $request['query_string'] = http_build_query($request['params']);
        $request['cacheable_query_string'] = $request['query_string'];

        return $request;
    }
    
    function is_auth_request($request) {
    	    return false;
    }
}
