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
			->with($this->callback(function($subject){
				return ($subject->getOriginalLocation() == 'sciencemesh/something/a-file.json');
			}));
		$result = $controller->EmptyRecycle($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testGetMDFolder(){
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->sciencemeshFolder->method("get")
			->with($this->equalTo("some/path"))
			->willReturn($testFolder);
		$testFolder->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FOLDER);
		$testFolder->method("getPath")->willReturn("/sciencemesh/some/path");
		$testFolder->method("getSize")->willReturn(1234);
		$testFolder->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$metadata = [
			"opaque" => [
					"map" => NULL,
			],
			"type" => 1,
			"id" => [
					"opaque_id" => "fileid-/some/path"
			],
			"checksum" => [
					"type" => 0,
					"sum" => "",
			],
			"etag" => "deadbeef",
			"mime_type" => "text/plain",
			"mtime" => [
					"seconds" => 1234567890
			],
			"path" => "/some/path",
			"permission_set" => [
					"add_grant" => false,
					"create_container" => false,
					"delete" => false,
					"get_path" => false,
					"get_quota" => false,
					"initiate_file_download" => false,
					"initiate_file_upload" => false,
					// "listGrants => false,
					// "listContainer => false,
					// "listFileVersions => false,
					// "listRecycle => false,
					// "move => false,
					// "removeGrant => false,
					// "purgeRecycle => false,
					// "restoreFileVersion => false,
					// "restoreRecycleItem => false,
					// "stat => false,
					// "updateGrant => false,
					// "denyGrant => false,
			],
			"size" => 12345,
			"canonical_metadata" => [
					"target" => NULL,
			],
			"arbitrary_metadata" => [
					"metadata" => [
							"some" => "arbi",
							"trary" => "meta",
							"da" => "ta",
					],
			],
	  ];
		$this->sciencemeshFolder->method("getPath")
			->willReturn("/sciencemesh");
		$this->sciencemeshFolder->method("get")
			->with($this->equalTo("some/path"))
			->willReturn($testFolder);
		$this->request->method("getParam")
			->with($this->equalTo("ref"))
			->willReturn([
				"resource_id" => [
					"storage_id" => "storage-id",
					"opaque_id" => "opaque-id"
				],
				"path" => "/some/path"
			]);
		$result = $controller->GetMD($this->userId);
		$this->assertEquals($result->getData(), $metadata);
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
		$metadata = [
			"opaque" => [
					"map" => NULL,
			],
			"type" => 1,
			"id" => [
					"opaque_id" => "fileid-/test.json"
			],
			"checksum" => [
					"type" => 0,
					"sum" => "",
			],
			"etag" => "deadbeef",
			"mime_type" => "text/plain",
			"mtime" => [
					"seconds" => 1234567890
			],
			"path" => "/test.json",
			"permission_set" => [
					"add_grant" => false,
					"create_container" => false,
					"delete" => false,
					"get_path" => false,
					"get_quota" => false,
					"initiate_file_download" => false,
					"initiate_file_upload" => false,
					// "listGrants => false,
					// "listContainer => false,
					// "listFileVersions => false,
					// "listRecycle => false,
					// "move => false,
					// "removeGrant => false,
					// "purgeRecycle => false,
					// "restoreFileVersion => false,
					// "restoreRecycleItem => false,
					// "stat => false,
					// "updateGrant => false,
					// "denyGrant => false,
			],
			"size" => 12345,
			"canonical_metadata" => [
					"target" => NULL,
			],
			"arbitrary_metadata" => [
					"metadata" => [
							"some" => "arbi",
							"trary" => "meta",
							"da" => "ta",
					],
			],
	  ];
		$this->sciencemeshFolder->method("getPath")->willReturn("/sciencemesh");
		$this->sciencemeshFolder->method("get")
			->with($this->equalTo("test.json"))
			->willReturn($testFile);

			$this->request->method("getParam")
			->with($this->equalTo("ref"))
			->willReturn([
				"resource_id" => [
					"storage_id" => "storage-id",
					"opaque_id" => "opaque-id"
				],
				"path" => "/test.json"
			]);
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
	
	public function testListFolderRoot(){
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->request->method("getParam")
			->with(($this->equalTo("ref")))
			->willReturn([
				"resource_id" => [
					"storage_id" => "storage-id",
					"opaque_id" => "opaque-id"
				],
				"path" => "/"
			]);

		$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
		$testFile->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FILE);
		$testFile->method("getMimetype")->willReturn("application/json");
		$testFile->method("getPath")->willReturn("/sciencemesh/test.json");
		$testFile->method("getSize")->willReturn(1234);
		$testFile->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
	
		$paramsMap = [
			["/",$this->sciencemeshFolder],
			["/test.json",$testFile]
		];
		$this->sciencemeshFolder->method("get")
								->will($this->returnValueMap($paramsMap));
		$folderContentsJSONData =  [
			[
				"opaque" => [
						"map" => NULL,
				],
				"type" => 1,
				"id" => [
						"opaque_id" => "fileid-/test.json"
				],
				"checksum" => [
						"type" => 0,
						"sum" => "",
				],
				"etag" => "deadbeef",
				"mime_type" => "text/plain",
				"mtime" => [
						"seconds" => 1234567890
				],
				"path" => "/test.json",
				"permission_set" => [
						"add_grant" => false,
						"create_container" => false,
						"delete" => false,
						"get_path" => false,
						"get_quota" => false,
						"initiate_file_download" => false,
						"initiate_file_upload" => false,
						// "listGrants => false,
						// "listContainer => false,
						// "listFileVersions => false,
						// "listRecycle => false,
						// "move => false,
						// "removeGrant => false,
						// "purgeRecycle => false,
						// "restoreFileVersion => false,
						// "restoreRecycleItem => false,
						// "stat => false,
						// "updateGrant => false,
						// "denyGrant => false,
				],
				"size" => 12345,
				"canonical_metadata" => [
						"target" => NULL,
				],
				"arbitrary_metadata" => [
						"metadata" => [
								"some" => "arbi",
								"trary" => "meta",
								"da" => "ta",
						],
				],
			],
		];
	  $folderContentsObjects = [ $testFile ];
		$this->sciencemeshFolder->method("getDirectoryListing")
			->willReturn($folderContentsObjects);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);

		$result = $controller->ListFolder($this->userId);
		$this->assertEquals($result->getData(),$folderContentsJSONData);
	}

	public function testListFolderNotFound(){
		$this->request->method("getParam")
			->with(($this->equalTo("ref")))
			->willReturn([
				"resource_id" => [
					"storage_id" => "storage-id",
					"opaque_id" => "opaque-id"
				],
				"path" => "/not/found"
			]);
	
		$paramsMap = [
			["/",$this->sciencemeshFolder],
			["/not/found", NULL]
		];
		$this->sciencemeshFolder->method("get")
								->will($this->returnValueMap($paramsMap));

		$this->sciencemeshFolder->method("getDirectoryListing")
			->willReturn(false);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);

		$result = $controller->ListFolder($this->userId);
		$this->assertEquals($result->getData(),$folderContentsJSONData);
	}

	public function testListFolderOther(){
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->request->method("getParam")
			->with(($this->equalTo("ref")))
			->willReturn([
				"resource_id" => [
					"storage_id" => "storage-id",
					"opaque_id" => "opaque-id"
				],
				"path" => "/some/path"
			]);
		$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
		$testFile->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FILE);
		$testFile->method("getMimetype")->willReturn("application/json");
		$testFile->method("getPath")->willReturn("/sciencemesh/some/path/test.json");
		$testFile->method("getSize")->willReturn(1234);
		$testFile->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?

		$testFolder->method('getPath')->willReturn("/sciencemesh/some/path");
		$paramsMap = [
			["/some/path",$testFolder],
			["/some/path/test.json",$testFile]
		];
		$this->sciencemeshFolder->method("get")
								->will($this->returnValueMap($paramsMap));
		$folderContentsJSONData =  [
			[
				"opaque" => [
						"map" => NULL,
				],
				"type" => 1,
				"id" => [
						"opaque_id" => "fileid-/some/path/test.json"
				],
				"checksum" => [
						"type" => 0,
						"sum" => "",
				],
				"etag" => "deadbeef",
				"mime_type" => "text/plain",
				"mtime" => [
						"seconds" => 1234567890
				],
				"path" => "/some/path/test.json",
				"permission_set" => [
						"add_grant" => false,
						"create_container" => false,
						"delete" => false,
						"get_path" => false,
						"get_quota" => false,
						"initiate_file_download" => false,
						"initiate_file_upload" => false,
						// "listGrants => false,
						// "listContainer => false,
						// "listFileVersions => false,
						// "listRecycle => false,
						// "move => false,
						// "removeGrant => false,
						// "purgeRecycle => false,
						// "restoreFileVersion => false,
						// "restoreRecycleItem => false,
						// "stat => false,
						// "updateGrant => false,
						// "denyGrant => false,
				],
				"size" => 12345,
				"canonical_metadata" => [
						"target" => NULL,
				],
				"arbitrary_metadata" => [
						"metadata" => [
								"some" => "arbi",
								"trary" => "meta",
								"da" => "ta",
						],
				],
			],
		];
	  $folderContentsObjects = [ $testFile ];
		$testFolder->method("getDirectoryListing")
			->willReturn($folderContentsObjects);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);

		$result = $controller->ListFolder($this->userId);
		$this->assertEquals($result->getData(),$folderContentsJSONData);
	}

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

	public function testListRecycle(){

		$data =[
			[
				"mimetype"=>"application/json",
				"path"=>"/some/path/to/file1.json",
				"size"=>1234,
				"basename"=>"file1.json",
				"timestamp"=>1234567890,
				'deleted'=>1234567890,
				"type"=>"file",
				"visibility"=>"public"
			]
		];
		$user =  $this->getMockBuilder("OCP\IUser")->getMock();
		$this->userManager->method("get")->willReturn($user);
		$item1 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item1->method("getOriginalLocation")
			->willReturn("sciencemesh/some/path/to/file1.json");
		$item2 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item2->method("getOriginalLocation")
			->willReturn("somethingElse/some/path/to/file2.json");
		$trashItems = [
			$item1,
			$item2
		];
		$this->trashManager->method("listTrashRoot")
			->willReturn($trashItems);
		$item1->method("getMimetype")->willReturn("application/json");
		$item1->method("getPath")->willReturn("file1.json");
		$item1->method("getSize")->willReturn(1234);
		$item1->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
		$item1->method("getDeletedTime")->willReturn(1234567890);
		$item1->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FILE);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->ListRecycle($this->userId);
		$this->assertEquals($result->getData(),$data);
	}

	public function testListRevisions(){
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->ListRevisions($this->userId);
		//$this->assertEquals($result->getData(),"Not implemented");
	}
//
	// FIX ISSUE # 20
	// public function testMove(){
	// 	$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
	// 	$this->sciencemeshFolder->method("get")
	// 		->willReturn($testFile);
	// 	$paramsMap = [
	// 		["from",NULL,"/sciencemesh/test"],
	// 		["to",NULL,"sciencemesh/production"]
	// 	];
	// 	$this->request->method("getParam")
	// 							->will($this->returnValueMap($paramsMap));
	// 	$controller = new RevaController(
	// 		$this->appName, $this->rootFolder, $this->request, $this->session,
	// 		$this->userManager, $this->urlGenerator, $this->userId, $this->config,
	// 		$this->userService, $this->trashManager
	// 	);
	// 	$result = $controller->Move($this->userId);
	// 	$this->assertEquals($result->getData(),"OK");
	// }

	public function testRemoveGrant(){
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->RemoveGrant($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testRestoreRecycleItem(){
		$this->request->method("getParam")->willReturn("/file1.json");
		$user =  $this->getMockBuilder("OCP\IUser")->getMock();
		$this->userManager->method("get")->willReturn($user);
		$item1 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item1->method("getOriginalLocation")
			->willReturn("sciencemesh/file1.json");
		$item2 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item2->method("getOriginalLocation")
			->willReturn("somethingElse/file2.json");
		$trashItems = [
			$item1,
			$item2
		];
		$this->trashManager->method("listTrashRoot")
			->willReturn($trashItems);
		$this->trashManager->method("restoreItem")
			->willReturn(null);
		$this->trashManager
			->expects($this->once())
			->method("restoreItem");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);

		$result = $controller->RestoreRecycleItem($this->userId);
		$this->assertEquals($result->getData(),"OK");
	}

	public function testRestoretRevision(){
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->RestoreRevision($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testSetArbitraryMetadatan(){
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->SetArbitraryMetadata($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testUnsetArbitraryMetadata(){
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->UnsetArbitraryMetadata($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testUpdateGrant(){
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$result = $controller->UpdateGrant($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testUpload(){
		$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
		$this->sciencemeshFolder->method("get")
			->with($this->equalTo("test.json"))
			->willReturn($testFile);
		$this->sciencemeshFolder->method("nodeExists")
			->with($this->equalTo("test.json"))
			->willReturn(true);
		$testFile->method("getContent")
			->willReturn("some-content");
		$testFile->method("getPath")
			->willReturn("/sciencemesh/test.json");
		$testFile->method('putContent')->willReturn(null);
		$this->request->method("getParam")->willReturn("/test.json");
		$this->request->put = "some-content";
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager
		);
		$testFile->expects($this->once())
			->method('putContent')
			->with($this->equalTo("some-content"));;

		$result = $controller->Upload($this->userId, "/test.json");
		$this->assertEquals($result->getData(),"OK");
	}

}
