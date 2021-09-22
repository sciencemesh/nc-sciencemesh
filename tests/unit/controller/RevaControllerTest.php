<?php

namespace OCA\ScienceMesh\Tests\Unit\Controller;

use PHPUnit_Framework_TestCase;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\IRootFolder;
use OCA\ScienceMesh\Controller\RevaController;
use OCA\ScienceMesh\Service\UserService;


class RevaControllerTest extends PHPUnit_Framework_TestCase {

	private $controller;
	private $appName = "sciencemesh";
	private $rootFolder;
	private $request;
	private $session;
	private $userManager;
	private $urlGenerator;
	private $userId = "einstein";
	private $config;
	private $userService;
	private $trashManager;

	public function setUp() {
		// Controller constructor arg names:
		// $AppName, IRootFolder $rootFolder, IRequest $request, ISession $session,
		// IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IConfig $config,
		// \OCA\ScienceMesh\Service\UserService $UserService, ITrashManager $trashManager)

		$this->rootFolder = $this->getMockBuilder("OCP\Files\IRootFolder")->getMock();
		$this->request = $this->getMockBuilder("OCP\IRequest")->getMock();
		$this->session =  $this->getMockBuilder("OCP\ISession")->getMock();
		$this->userManager =  $this->getMockBuilder("OCP\IUserManager")->getMock();
		$this->urlGenerator =  $this->getMockBuilder("OCP\IURLGenerator")->getMock();
		$this->config =  $this->getMockBuilder("OCP\IConfig")->getMock();
		$this->userService  =  new UserService($this->session);

		$this->trashManager =  $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashManager")->getMock();

	}

	public function testAuthenticateOK() {
		$user =  $this->getMockBuilder("OCP\IUser")->getMock();
		$this->request->method("getParam")->willReturn("whatever");
	  $this->userManager->method("checkPassword")->willReturn($user);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->Authenticate($this->userId);
		$this->assertEquals($result->getData(), "Logged in");
	}


	public function testAuthenticateWrong() {
		$this->request->method("getParam")->willReturn("whatever");
	  $this->userManager->method("checkPassword")->willReturn(false);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->Authenticate($this->userId);
		$this->assertEquals($result->getData(), "Username / password not recognized");
	}


	public function testCreateDir(){
		$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$userFolder->method("nodeExists")->willReturn(true);
		$userFolder->method("get")->willReturn($sciencemeshFolder);
		$this->rootFolder->method("getUserFolder")->willReturn($userFolder);
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$sciencemeshFolder->expects($this->once())
			->method("newFolder")
			->with($this->equalTo("test"));
		$result = $controller->createDir($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}


	public function testCreateHome(){
		$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$userFolder->method("nodeExists")->willReturn(true);
		$this->rootFolder->method("getUserFolder")->willReturn($userFolder);
		$userFolder->method("get")->willReturn($sciencemeshFolder);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->CreateHome($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testCreateReference(){
		$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->rootFolder->method("getUserFolder")->willReturn($userFolder);
		$userFolder->method("nodeExists")->willReturn(true);
		$userFolder->method("get")->willReturn($sciencemeshFolder);
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
 		$result = $controller->CreateReference($this->userId);
		$this->assertEquals($result->getData(), "Not implemented");
	}

	public function testDelete(){
		$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$userFolder->method("nodeExists")->willReturn(true);
		$userFolder->method("get")->willReturn($sciencemeshFolder);
		$sciencemeshFolder->method("get")->willReturn($testFolder);
		$sciencemeshFolder->method("nodeExists")->willReturn(true);
		$this->rootFolder->method("getUserFolder")->willReturn($userFolder);
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$sciencemeshFolder->expects($this->once())
			->method("get")
			->with($this->equalTo("test"));
	  $testFolder->expects($this->once())->method("delete");
		$result = $controller->Delete($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	// public function testEmptyRecycle(){
	//
	// 	$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
	// 	$sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
	// 	$user =  $this->getMockBuilder("OCP\IUser")->getMock();
	// 	$trashItems = ?????????????
	// 	$userFolder->method("nodeExists")
	// 								->willReturn(true);
	// 	$userFolder->method("get")
	// 								->willReturn($sciencemeshFolder);
	// 	$sciencemeshFolder->method("get")
	// 								->willReturn($testFolder);
	// 	$this->rootFolder->method("getUserFolder")
	// 								->willReturn($userFolder);
	// 	$controller = new RevaController(
	// 		$this->appName, $this->rootFolder, $this->request, $this->session,
	// 		$this->userManager, $this->urlGenerator, $this->userId, $this->config,
	// 		$this->userService, $this->trashManager
	// 	);
	//
	// 	$this->userManager->method("get")
	// 										->willReturn($user);
	// 	$this->trashManager->method("listTrashRoot")
	// 										->willReturn($trashItems);
	// 	$result = $controller->EmptyRecycle($this->userId);
	// 	$this->assertEquals($result->getData(), "OK");
	// }

	public function testGetMDFolder(){
		$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$userFolder->method("nodeExists")->willReturn(true);
		$this->rootFolder->method("getUserFolder")->willReturn($userFolder);
		$userFolder->method("get")->willReturn($sciencemeshFolder);
		$sciencemeshFolder->method("get")->willReturn($testFolder);
		$sciencemeshFolder->method("nodeExists")->willReturn(true);
		$sciencemeshFolder->method("getPath")->willReturn("/sciencemesh");
		$testFolder->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FOLDER);
		$testFolder->method("getPath")->willReturn("/sciencemesh/test");
		$testFolder->method("getSize")->willReturn(1234);
		$testFolder->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$metadata =[
			"mimetype"=>"directory",
			"path"=>"test",
			"size"=>1234,
			"basename"=>"test",
			"timestamp"=>1234567890,
			"type"=>"dir",
			"visibility"=>"public"
		];

		$this->request->method("getParam")->willReturn("/test");
		$result = $controller->GetMD($this->userId);
		$this->assertEquals($result->getData(),$metadata);

	}

	public function testGetMDFile(){
		$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$userFolder->method("nodeExists")->willReturn(true);
		$this->rootFolder->method("getUserFolder")->willReturn($userFolder);
		$userFolder->method("get")->willReturn($sciencemeshFolder);
		$sciencemeshFolder->method("get")->willReturn($testFolder);
		$sciencemeshFolder->method("nodeExists")->willReturn(true);
		$sciencemeshFolder->method("getPath")->willReturn("/sciencemesh");
		$testFolder->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FILE);
		$testFolder->method("getMimetype")->willReturn("application/json");
		$testFolder->method("getPath")->willReturn("/sciencemesh/test.json");
		$testFolder->method("getSize")->willReturn(1234);
		$testFolder->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$metadata =[
			"mimetype"=>"application/json",
			"path"=>"test.json",
			"size"=>1234,
			"basename"=>"test.json",
			"timestamp"=>1234567890,
			"type"=>"file",
			"visibility"=>"public"
		];


		$this->request->method("getParam")->willReturn("/test");
		$result = $controller->GetMD($this->userId);
		$this->assertEquals($result->getData(),$metadata);

	}
}
