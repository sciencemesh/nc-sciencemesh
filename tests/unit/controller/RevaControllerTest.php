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


		// For initializeStorage, see
		// https://github.com/pondersource/nc-sciencemesh/blob/febe370de013cd8cd21d323c66d00cba54671dd7/lib/Controller/RevaController.php#L60-L64
		$userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->rootFolder->method("getUserFolder")->willReturn($userFolder);
		$this->sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$userFolder->method("nodeExists")->willReturn(true);
		$userFolder->method("get")
			->with($this->equalTo("sciencemesh"))
			->willReturn($this->sciencemeshFolder);
		$this->sciencemeshFolder->method("nodeExists")->willReturn(true);
		$this->sciencemeshFolder->method("getPath")->willReturn("/sciencemesh");
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
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$this->sciencemeshFolder->expects($this->once())
			->method("newFolder")
			->with($this->equalTo("test"));
		$result = $controller->createDir($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}


	public function testCreateHome(){
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->CreateHome($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testCreateReference(){
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
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->sciencemeshFolder->method("get")
			->with($this->equalTo("test"))
			->willReturn($testFolder);
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$this->sciencemeshFolder->expects($this->once())
			->method("get")
			->with($this->equalTo("test"));
		$testFolder->expects($this->once())->method("delete");
		$result = $controller->Delete($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testEmptyRecycle(){
		$user =  $this->getMockBuilder("OCP\IUser")->getMock();
		$this->userManager->method("get")->willReturn($user);

		$item1 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item1->method("getOriginalLocation")
			->willReturn("sciencemesh/something/a-file.json");
		$item2 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item2->method("getOriginalLocation")
			->willReturn("somethingElse/bla.json");

		$trashItems = [
			$item1,
			$item2
		];
		$this->trashManager->method("listTrashRoot")
			->willReturn($trashItems);
		$this->trashManager->method("removeItem")
			->willReturn(null);

		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);

		$this->trashManager
			->expects($this->once())
			->method("removeItem")
			->with($this->equalTo($item1));
		$result = $controller->EmptyRecycle($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testGetMDFolder(){
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->sciencemeshFolder->method("get")
			->with($this->equalTo("test"))
			->willReturn($testFolder);
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
		$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
		$this->sciencemeshFolder->method("get")
			->with($this->equalTo("test.json"))
			->willReturn($testFile);
		$testFile->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FILE);
		$testFile->method("getMimetype")->willReturn("application/json");
		$testFile->method("getPath")->willReturn("/sciencemesh/test.json");
		$testFile->method("getSize")->willReturn(1234);
		$testFile->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
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
		$this->request->method("getParam")->willReturn("/test.json");
		$result = $controller->GetMD($this->userId);
		$this->assertEquals($result->getData(),$metadata);
	}

	public function testGetPathByID(){

		$paramsMap = [
			["storage_id",NULL,"some-storage-id"],
			["opaque_id",NULL,"some-opaque-id"]
		];
		$this->request->method("getParam")
								->will($this->returnValueMap($paramsMap));
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->GetPathByID($this->userId);
		$this->assertEquals($result->getData(),'/foo');
	}

	public function testInitiateUpload(){

		$response = [
			"simple" => "yes",
			"tus" => "yes"
		];
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->InitiateUpload($this->userId);
		$this->assertEquals($result->getData(),$response);
	}

	// public function testListFolder(){
	// 	$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
	// 	$this->request->method("getParam")->willReturn("/test.json");
	// 	$folderContents = [
	// 	"data"=>[],
	// 	"headers"=>[
	// 		"Cache-Control"=>"no-cache, no-store, must-revalidate",
	// 		"Content-Type"=>"application/json; charset=utf-8"
	// 	],
	// 	"cookies"=>[],
	// 	"status"=>123,
	// 	"lastModified"=>NULL,
	// 	"ETag"=>NULL,
	// 	"contentSecurityPolicy"=>NULL,
	// 	"featurePolicy"=>NULL,
	// 	"throttled"=>false,
	// 	"throttleMetadata"=>[]
	// 	];
	// 	$this->sciencemeshFolder->method("get")
	// 		->with($this->equalTo("/test.json"))
	// 		->willReturn($testFile);
	// 	$this->sciencemeshFolder->method("getDirectoryListing")
	// 		->with($this->equalTo("/test.json"))
	// 		->willReturn($folderContents);
	// 	$controller = new RevaController(
	// 		$this->appName, $this->rootFolder, $this->request, $this->session,
	// 		$this->userManager, $this->urlGenerator, $this->userId, $this->config,
	// 		$this->userService, $this->trashManager
	// 	);
	//
	// 	$result = $controller->ListFolder($this->userId);
	// 	var_dump($result);
	// 	//$this->assertEquals($result->getData(),$folderContents);
	// }
	public function testListGrants(){
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->ListGrants($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}
}
