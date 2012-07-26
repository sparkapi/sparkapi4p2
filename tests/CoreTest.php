<?php
require_once 'tests/TestHelpers.php';
require_once 'PHPUnit/Framework/TestCase.php';

class SparkApi_CoreTest extends PHPUnit_Framework_TestCase {
	private $core;

	public function setUp() {
		$this->core = new SparkAPI_Core();
	}	

	public function tearDown() {
		/* ... */
	}

	public function testInstantiation() {
		$this->assertNotNull($this->core);
	}

	public function testMakeSendableBodyWrapsWithD() {
		$body = array("ListingIds" => array("20100000000000000000000000",
			                                "20100000000000000000000000"));
		$this->assertEquals( json_encode(array("D"=>$body)), 
			$this->core->make_sendable_body($body));
	}

	public function testParseCacheTime() {
		$cases = array(
			"1w"  => "604800",
			"1d"  => "86400",
			"1h"  => "3600",
			"1m"  => "60",
			"86400" => "86400"
		);

		foreach ($cases as $key => $value) {
			$this->assertEquals($value, $this->core->parse_cache_time($key));
		}
	}
}
