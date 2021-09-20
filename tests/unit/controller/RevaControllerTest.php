<?php

namespace OCA\ScienceMesh\Tests\Unit\Controller;

use PHPUnit_Framework_TestCase;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IRootFolder;
use OCA\ScienceMesh\Controller\RevaController;
use OCA\ScienceMesh\Service\UserService;


class RevaControllerTest extends PHPUnit_Framework_TestCase {
	private $controller;
	private $userId = 'john';

	public function setUp() {
		
		// arg names: $AppName, IRootFolder $rootFolder, IRequest $request, ISession $session,
		// IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IConfig $config,
		// \OCA\ScienceMesh\Service\UserService $UserService, ITrashManager $trashManager) 
	  $appName = 'sciencemesh';
		$rootFolder = $this->getMockBuilder('OCP\Files\IRootFolder')->getMock();
		$request = $this->getMockBuilder('OCP\IRequest')->getMock();
		$session =  $this->getMockBuilder('OCP\ISession')->getMock();
		$userManager =  $this->getMockBuilder('OCP\IUserManager')->getMock();
		$urlGenerator =  $this->getMockBuilder('OCP\IURLGenerator')->getMock();
		$config =  $this->getMockBuilder('OCP\IConfig')->getMock();
		$userService  =  new UserService($session);
		$trashManager =  $this->getMockBuilder('OCA\Files_Trashbin\Trash\ITrashManager')->getMock();
		
		$this->controller = new RevaController(
			$appName, $rootFolder, $request, $session,
			$userManager, $urlGenerator, $this->userId, $config,
			$userService, $trashManager
		);
	}

	public function testGetFileSystem() {
		$result = $this->controller->Authenticate(5);

		$this->assertEquals('index', $result->getTemplateName());
		$this->assertTrue($result instanceof TemplateResponse);
	}

}
