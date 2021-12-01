<?php

namespace OCA\ScienceMesh\Tests\Unit;

use PHPUnit_Framework_TestCase;
use OCA\ScienceMesh\Plugins\ScienceMeshGenerateTokenPlugin;

class ScienceMeshGenerateTokenPluginTest extends PHPUnit_Framework_TestCase {
	
	/** @var IUserManager */
	private $userManager;
	private $config;
	private $session;
	private $httpClient;

	public function setUp() {
		$this->userManager = $this->getMockBuilder("OCP\IUserManager")->getMock();
		$this->config = $this->getMockBuilder("OCP\IConfig")->getMock();
		$this->session = $this->getMockBuilder("OCP\IUserSession")->getMock();
		$this->httpClient = $this->getMockBuilder("OCA\ScienceMesh\RevaHttpClient")->getMock();
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$user = $this->getMockBuilder("OCP\IUser")->getMock();
	}

	public function testGenerateToken() {
		$response = [
			"status" => [
				"code" => 1,
				"trace" => "00000000000000000000000000000000"
			],
			"invite_token" => [
				"token" => "161405dd-05f0-4806-8b42-1482b3185c63",
				"user_id" => [
					"idp" => "some-idp",
					"opaque_id" => "einstein",
					"type" => 1
				],
				"expiration" => [
					"seconds" => gmdate("H:i:s", 1638025012)
				]
			]
		];
	   
		$sciencMeshToken = new ScienceMeshGenerateTokenPlugin($this->config, $this->userManager,$this->session, $this->httpClient);
		
		$this->assertEquals($sciencMeshToken->getGenerateTokenResponse(), null);
	}

	public function testGenerateTokenFromReva() {
		$request = false;
	   
		$sciencMeshToken = new ScienceMeshGenerateTokenPlugin($this->config, $this->userManager,$this->session, $this->httpClient);
		
		$this->assertEquals($sciencMeshToken->generateTokenFromReva(), $request);
	}
}
