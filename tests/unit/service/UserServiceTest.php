<?php

namespace OCA\ScienceMesh\Tests\Unit\Controller;

use PHPUnit_Framework_TestCase;

use OCA\ScienceMesh\Service\UserService;

class UserServiceTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->session = $this->getMockBuilder("OCP\IUserSession")->getMock();
		$this->userService = new UserService($this->session);
	}

	public function testLogin() {
		$this->session
			->expects($this->once())
			->method('login')
			->with($this->equalTo("a"), $this->equalTo("b"))
			->willReturn(null);
		$this->userService->login("a", "b");
	}

	public function testLogout() {
		$this->session
			->expects($this->once())
			->method("logout")
			->with() // FIXME: this does nothing, see https://stackoverflow.com/questions/9475705/check-that-mocks-method-is-called-without-any-parameters-passed-in-phpunit
			->willReturn(null);
		$this->userService->logout();
	}
}
