<?php

namespace OCA\ScienceMesh\Tests\Integration\SharesCrud;


use PHPUnit_Framework_TestCase;

const API_USER = "alice";
const API_PASS = "alice123";
const API_BASE = "https://nc1.docker/index.php/apps/sciencemesh/~alice/api/";

function curlPost($apiPath, $params = []) {
	$ch = curl_init();
	
	curl_setopt($ch, CURLOPT_URL, API_BASE.$apiPath);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_PRETTY_PRINT));
	curl_setopt($ch, CURLOPT_USERPWD, API_USER.":".API_PASS);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	$output = curl_exec($ch);
	curl_close($ch);

	return $output;
}

/**
 * This test lists shares, creates one, lists them again, removes it, lists them again
 * At least that's the plan :)
 */
class SharesCrudTest extends PHPUnit_Framework_TestCase {
	private $container;

	public function setUp() {
	}

	public function testCrudCycleForReceived() {
    $output = curlPost("ocm/ListReceivedShares", []);
		$this->assertEquals("[]", $output);
    // $output = curlPost("ocm/addReceivedShare", [
		// 	"md" => [
		// 		"opaque_id" => "fileid-einstein%2Fmy-folder",
		// 	],
		// 	"g" => [
		// 		"grantee" => [
		// 			"type" => 1,
		// 		],
		// 		"Id" => [
		// 			"UserId" => [
		// 				"idp" => "cesnet.cz",
		// 				"opaque_id" => "marie",
		// 				"type" => 1,
		// 			],
		// 		],
		// 	],
		// 	"provider_domain" => "cern.ch",
		// 	"resource_type" => "file",
		// 	"provider_id" => 2,
		// 	"owner_display_name" => "Albert Einstein",
		// 	"protocol" => [
		// 		"name" => "webdav",
		// 		"options" => [
		// 			"sharedSecret" => "secret",
		// 			"permissions" => "webdav-property",
		// 		],
		// 	],
		// ]);
		// $this->assertEquals("[]", $output);
    // $output = curlPost("ocm/ListReceivedShares", []);
		// $this->assertEquals("[]", $output);
    // $output = curlPost("ocm/UnShare", []);
		// $this->assertEquals("[]", $output);
    // $output = curlPost("ocm/ListReceivedShares", []);
		// $this->assertEquals("[]", $output);
	}
}
