<?php

class SparkAPI_Hybrid extends SparkAPI_OAuth implements SparkAPI_AuthInterface {
	function authentication_endpoint_uri($additional_params = array()) {
		$params = array(
			"openid.spark.combined_flow" => "true",
			"openid.mode"                => "checkid_setup",
			"openid.spark.client_id"     => $this->api_client_id,
			"openid.return_to"           => $this->oauth_redirect_uri
		);
		return $this->authentication_host() . "openid?" . http_build_query(array_merge($params, $additional_params));
	}
}
