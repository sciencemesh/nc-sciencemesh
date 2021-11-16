<?php

namespace OCA\ScienceMesh\Tests\Unit;

use PHPUnit_Framework_TestCase;

use OCA\ScienceMesh\ServerConfig;

class ServerConfigTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->config = $this->getMockBuilder("OCP\IConfig")->getMock();
		$this->serverConfig = new ServerConfig($this->config);
	}

	public function testApiKey() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","apiKey","decafbad");
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","apiKey")
			->willReturn("decafbad");
		$this->serverConfig->setApiKey("decafbad");
		$this->assertEquals("decafbad", $this->serverConfig->getApiKey());
	}
	public function testSiteName() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","siteName","ScienceMesh");
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","siteName")
			->willReturn("ScienceMesh");
		$this->serverConfig->setSiteName("ScienceMesh");
		$this->assertEquals("ScienceMesh", $this->serverConfig->getSiteName());
	}
	public function testSiteUrl() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","siteUrl","http://localhost.localdomain");
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","siteUrl")
			->willReturn("http://localhost.localdomain");
		$this->serverConfig->setSiteUrl("http://localhost.localdomain");
		$this->assertEquals("http://localhost.localdomain", $this->serverConfig->getSiteUrl());
	}
	public function testSiteId() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","siteId","deadbeef");
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","siteId")
			->willReturn("deadbeef");
		$this->serverConfig->setSiteId("deadbeef");
		$this->assertEquals("deadbeef", $this->serverConfig->getSiteId());
	}
	public function testCountry() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","country","Zarmonien");
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","country")
			->willReturn("Zarmonien");
		$this->serverConfig->setCountry("Zarmonien");
		$this->assertEquals("Zarmonien", $this->serverConfig->getCountry());
	}
	public function testIopUrl() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","iopUrl","http://www.example.mock");
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","iopUrl")
			->willReturn("http://www.example.mock");
		$this->serverConfig->setIopUrl("http://www.example.mock");
		$this->assertEquals("http://www.example.mock", $this->serverConfig->getIopUrl());
	}
	public function testNumUsers() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","numUsers",5);
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","numUsers")
			->willReturn(5);
		$this->serverConfig->setNumUsers(5);
		$this->assertEquals(5, $this->serverConfig->getNumUsers());
	}
	public function testNumFiles() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","numFiles",5);
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","numFiles")
			->willReturn(5);
		$this->serverConfig->setNumFiles(5);
		$this->assertEquals(5, $this->serverConfig->getNumFiles());
	}
	public function testNumStorage() {
		$this->config
			->expects($this->once())
			->method("setAppValue")
			->with("sciencemesh","numStorage",5);
		$this->config
			->expects($this->once())
			->method("getAppValue")
			->with("sciencemesh","numStorage")
			->willReturn(5);
		$this->serverConfig->setNumStorage(5);
		$this->assertEquals(5, $this->serverConfig->getNumStorage());
	}
}
