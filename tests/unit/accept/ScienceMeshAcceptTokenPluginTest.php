<?php

namespace OCA\ScienceMesh\Tests\Unit;

use PHPUnit_Framework_TestCase;
use OCA\ScienceMesh\Plugins\ScienceMeshAcceptTokenPlugin;

class ScienceMeshAcceptTokenPluginTest extends PHPUnit_Framework_TestCase {
	
	/** @var IUserManager */
	private $userManager;
	private $config;
	private $session;
	private $httpClient;
	private $request;

	public function setUp() {
		$this->userManager = $this->getMockBuilder("OCP\IUserManager")->getMock();
		$this->config = $this->getMockBuilder("OCP\IConfig")->getMock();
		$this->session = $this->getMockBuilder("OCP\IUserSession")->getMock();
		$this->httpClient = $this->getMockBuilder("OCA\ScienceMesh\RevaHttpClient")->getMock();
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$user = $this->getMockBuilder("OCP\IUser")->getMock();
		$this->request = $this->getMockBuilder("OCP\IRequest")->getMock();
	}

	public function testAcceptFromRevaBadRequest() {
		$sciencMeshAccept = new ScienceMeshAcceptTokenPlugin($this->config, $this->userManager,$this->session, $this->httpClient, $this->request);
		
		$this->assertEquals($sciencMeshAccept->getAcceptTokenResponse(), null);
	}
}
