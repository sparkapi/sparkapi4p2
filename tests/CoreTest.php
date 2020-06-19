<?php

class SparkApi_CoreTest extends \PHPUnit\Framework\TestCase {
	private $core;

	public function setUp() {
		$this->core = new SparkAPI_Core();
	}

	public function testInstantiation() {
		$this->assertNotNull($this->core);
	}

	public function testMakeSendableBodyWrapsWithD() {
		$body = array("ListingIds" => array("20100000000000000000000000",
			                                "20100000000000000000000000"));
		$this->assertEquals(json_encode(array("D"=>$body)), $this->core->make_sendable_body($body));
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

	public function testSetErrors() {
		$this->core->SetErrors(null, "This is a random error");
		$this->assertNull($this->core->last_error_code);
		$this->assertEquals("This is a random error", $this->core->last_error_mess);

		$this->core->SetErrors(1020, "Some message");
		$this->assertEquals(1020, $this->core->last_error_code);
		$this->assertEquals("Some message", $this->core->last_error_mess);
	}

	public function testResetErrors() {
		$this->core->SetErrors(1020, "This is a random error");
		$this->core->ResetErrors();
		$this->assertFalse($this->core->last_error_code);
		$this->assertFalse($this->core->last_error_mess);
	}

	public function testGetErrors() {
		$this->core->ResetErrors();
		$this->assertFalse($this->core->GetErrors());

		$this->core->SetErrors(1020, "This is a random error");
		$this->assertEquals("1020 - This is a random error", $this->core->GetErrors());
	}
}
