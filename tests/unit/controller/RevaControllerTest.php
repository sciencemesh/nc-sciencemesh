<?php

namespace OCA\ScienceMesh\Tests\Unit\Controller;

use PHPUnit_Framework_TestCase;

use OCA\ScienceMesh\Controller\RevaController;
use OCA\ScienceMesh\Service\UserService;

class RevaControllerTest extends PHPUnit_Framework_TestCase {
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
	private $shareManager;
	private $groupManager;
	private $cloudFederationProviderManager;
	private $factory;
	private $cloudIdManager;
	private $logger;
	private $appManager;
	private $l;


	private $lockedNode;
	private $controller;

	public $existingsMap = [
		["sciencemesh/not/found", false],
		["sciencemesh/test", true],
		["sciencemesh/test.json", true],
		["sciencemesh/some/path", true],
		["sciencemesh", true],
		['sciencemesh/emptyFolder', true],
	];

	public function setUp() {
		$this->rootFolder = $this->getMockBuilder("OCP\Files\IRootFolder")->getMock();
		$this->request = $this->getMockBuilder("OCP\IRequest")->getMock();
		$this->session = $this->getMockBuilder("OCP\ISession")->getMock();
		$this->userManager = $this->getMockBuilder("OCP\IUserManager")->getMock();
		$this->urlGenerator = $this->getMockBuilder("OCP\IURLGenerator")->getMock();

		$this->config = $this->getMockBuilder("OCP\IConfig")->getMock();
		$this->userService = new UserService($this->session);

		$this->trashManager = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashManager")->getMock();
		$this->shareManager = $this->getMockBuilder("OCP\Share\IManager")->getMock();
		$this->groupManager = $this->getMockBuilder("OCP\IGroupManager")->getMock();
		$this->cloudFederationProviderManager = $this->getMockBuilder("OCP\Federation\ICloudFederationProviderManager")->getMock();
		$this->factory = $this->getMockBuilder("OCP\Federation\ICloudFederationFactory")->getMock();
		$this->cloudIdManager = $this->getMockBuilder("OCP\Federation\ICloudIdManager")->getMock();
		$this->logger = $this->getMockBuilder("Psr\Log\LoggerInterface")->getMock();
		;
		$this->appManager = $this->getMockBuilder("OCP\App\IAppManager")->getMock();
		$this->l = $this->getMockBuilder("OCP\IL10N")->getMock();

		$this->userFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		// For initializeStorage, see
		// https://github.com/pondersource/nc-sciencemesh/blob/febe370de013cd8cd21d323c66d00cba54671dd7/lib/Controller/RevaController.php#L60-L64
		$this->rootFolder->method("getUserFolder")->willReturn($this->userFolder);
		$this->sciencemeshFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->userFolder->method("nodeExists")
			->will($this->returnValueMap($this->existingsMap));

		$this->sciencemeshFolder->method("nodeExists")->willReturn(true);
		$this->sciencemeshFolder->method("getPath")->willReturn("/sciencemesh");
		$this->shareProvider = $this->getMockBuilder("OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider")->disableOriginalConstructor()->getMock();
	}

	public function testAuthenticateOK() {
		$user = $this->getMockBuilder("OCP\IUser")->getMock();
		$this->request->method("getParam")->willReturn("whatever");
		$this->userManager->method("checkPassword")->willReturn($user);
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
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
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->Authenticate($this->userId);
		$this->assertEquals($result->getData(), "Username / password not recognized");
	}


	public function testCreateDir() {
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$this->userFolder->expects($this->once())
			->method("newFolder")
			->with($this->equalTo("sciencemesh/test"));
		$result = $controller->createDir($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}


	public function testCreateHome() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->CreateHome($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testCreateReference() {
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->CreateReference($this->userId);
		$this->assertEquals($result->getData(), "Not implemented");
	}

	public function testDelete() {
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$paramsMap = [
			["sciencemesh", $this->sciencemeshFolder],
			["sciencemesh/test", $testFolder]
		];
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/test"))
			->will($this->returnValueMap($paramsMap));
		$this->request->method("getParam")->willReturn("/test");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);

		$testFolder->expects($this->once())->method("delete");
		$result = $controller->Delete($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testEmptyRecycle() {
		$user = $this->getMockBuilder("OCP\IUser")->getMock();
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
			  $this->userService, $this->trashManager , $this->shareManager,
				$this->groupManager, $this->cloudFederationProviderManager,
				$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
			);
		$this->trashManager
			->expects($this->once())
			->method("removeItem")
			->with($this->callback(function ($subject) {
				return ($subject->getOriginalLocation() == 'sciencemesh/something/a-file.json');
			}));
		$result = $controller->EmptyRecycle($this->userId);
		$this->assertEquals($result->getData(), "OK");
	}

	public function testGetMDFolder() {
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/some/path"))
			->willReturn($testFolder);
		$testFolder->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FOLDER);
		$testFolder->method("getPath")->willReturn("/sciencemesh/some/path");
		$testFolder->method("getSize")->willReturn(1234);
		$testFolder->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$metadata = [
			"opaque" => [
				"map" => null,
			],
			"type" => 2,
			"id" => [
				"opaque_id" => "fileid-/some/path"
			],
			"checksum" => [
				"type" => 0,
				"sum" => "",
			],
			"etag" => "deadbeef",
			"mime_type" => "directory",
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
				"target" => null,
			],
			"arbitrary_metadata" => [
				"metadata" => [
					"some" => "arbi",
					"trary" => "meta",
					"da" => "ta",
				],
			],
			"owner" => [
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
			],
		];
		$this->userFolder->method("getPath")
			->willReturn("");
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/some/path"))
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

	public function testGetMDFile() {
		$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/test.json"))
			->willReturn($testFile);
		$testFile->method("getType")->willReturn(\OCP\Files\FileInfo::TYPE_FILE);
		$testFile->method("getMimetype")->willReturn("application/json");
		$testFile->method("getPath")->willReturn("/sciencemesh/test.json");
		$testFile->method("getSize")->willReturn(1234);
		$testFile->method("getMTime")->willReturn(1234567890); // should this be seconds or milliseconds?
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$metadata = [
			"opaque" => [
				"map" => null,
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
			"mime_type" => "application/json",
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
				"target" => null,
			],
			"arbitrary_metadata" => [
				"metadata" => [
					"some" => "arbi",
					"trary" => "meta",
					"da" => "ta",
				],
			],
			"owner" => [
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
			],
		];
		$this->userFolder->method("getPath")->willReturn("");
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/test.json"))
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

	public function testGetPathByID() {
		$paramsMap = [
			["storage_id",null,"some-storage-id"],
			["opaque_id",null,"some-opaque-id"]
		];
		$this->request->method("getParam")
								->will($this->returnValueMap($paramsMap));
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->GetPathByID($this->userId);
		$this->assertEquals($result->getStatus(),200);
	}

	public function testInitiateUpload() {
		$response = [
			"simple" => "yes",
			"tus" => "yes"
		];
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->InitiateUpload($this->userId);
		$this->assertEquals($result->getData(),$response);
	}

	public function testListFolderRoot() {
		// $testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
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
			["sciencemesh", $this->sciencemeshFolder],
			["sciencemesh/test.json", $testFile]
		];
		$this->userFolder->method("get")
								->will($this->returnValueMap($paramsMap));

		$this->userFolder->method("getPath")->willReturn("");
		$this->sciencemeshFolder->method("getPath")->willReturn("/sciencemesh");

		$folderContentsJSONData = [
			[
				"opaque" => [
					"map" => null,
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
				"mime_type" => "application/json",
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
					"target" => null,
				],
				"arbitrary_metadata" => [
					"metadata" => [
						"some" => "arbi",
						"trary" => "meta",
						"da" => "ta",
					],
				],
				"owner" => [
					"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				],
			],
		];
		$folderContentsObjects = [ $testFile ];
		$this->sciencemeshFolder->method("getDirectoryListing")
			->willReturn($folderContentsObjects);
		$controller = new RevaController(
				$this->appName, $this->rootFolder, $this->request, $this->session,
				$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			  $this->userService, $this->trashManager , $this->shareManager,
				$this->groupManager, $this->cloudFederationProviderManager,
				$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
			);

		$result = $controller->ListFolder($this->userId);
		$this->assertEquals($result->getData(),$folderContentsJSONData);
	}

	public function testListFolderNotFound() {
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
			["sciencemesh",$this->sciencemeshFolder],
			["not/found", null]
		];
		$this->sciencemeshFolder->method("getDirectoryListing")
			->willReturn(false);
		$controller = new RevaController(
				$this->appName, $this->rootFolder, $this->request, $this->session,
				$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			  $this->userService, $this->trashManager , $this->shareManager,
				$this->groupManager, $this->cloudFederationProviderManager,
				$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
			);
		$this->userFolder->method("get")
			->will($this->returnValueMap($paramsMap));

		$result = $controller->ListFolder($this->userId);
		$this->assertEquals($result->getStatus(), 404);
	}

	public function testListFolderEmpty() {
		$this->request->method("getParam")
				->with(($this->equalTo("ref")))
				->willReturn([
					"resource_id" => [
						"storage_id" => "storage-id",
						"opaque_id" => "opaque-id"
					],
					"path" => "/emptyFolder"
				]);

		$this->sciencemeshFolder->method("getDirectoryListing")
				->willReturn(false);
		$controller = new RevaController(
					$this->appName, $this->rootFolder, $this->request, $this->session,
					$this->userManager, $this->urlGenerator, $this->userId, $this->config,
				  $this->userService, $this->trashManager , $this->shareManager,
					$this->groupManager, $this->cloudFederationProviderManager,
					$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
				);

		$result = $controller->ListFolder($this->userId);
		$this->assertEquals($result->getData(), []);
		$this->assertEquals($result->getStatus(), 200);
	}

	public function testListFolderOther() {
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
		$this->userFolder->method("getPath")->willReturn("");

		$paramsMap = [
			["sciencemesh/some/path",$testFolder],
			["sciencemesh/some/path/test.json",$testFile]
		];
		$this->userFolder->method("get")
								->will($this->returnValueMap($paramsMap));
		$folderContentsJSONData = [
			[
				"opaque" => [
					"map" => null,
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
				"mime_type" => "application/json",
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
					"target" => null,
				],
				"arbitrary_metadata" => [
					"metadata" => [
						"some" => "arbi",
						"trary" => "meta",
						"da" => "ta",
					],
				],
				"owner" => [
					"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				],
			],
		];
		$folderContentsObjects = [ $testFile ];
		$testFolder->method("getDirectoryListing")
			->willReturn($folderContentsObjects);
		$controller = new RevaController(
				$this->appName, $this->rootFolder, $this->request, $this->session,
				$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			  $this->userService, $this->trashManager , $this->shareManager,
				$this->groupManager, $this->cloudFederationProviderManager,
				$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
			);

		$result = $controller->ListFolder($this->userId);
		$this->assertEquals($result->getData(),$folderContentsJSONData);
	}

	public function testListGrants() {
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->ListGrants($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testListRecycle() {
		$data = [
			[
				"opaque" => [
					"map" => null,
				],
				"key" => "/some/path/to/file1.json",
				"ref" => [
					"resource_id" => [
						"map" => null,
					],
					"path" => "/some/path/to/file1.json",
				],
				"size" => 12345,
				"deletion_time" => [
					"seconds" => 1234567890
				]
			]];
		$user = $this->getMockBuilder("OCP\IUser")->getMock();
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
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->ListRecycle($this->userId);
		$this->assertEquals($result->getData(),$data);
	}

	public function testListRevisions() {
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->ListRevisions($this->userId);
		//$this->assertEquals($result->getData(),"Not implemented");
	}
//
	// FIX ISSUE # 20
	// public function testMove(){
	// 	$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
	// 	$this->userFolder->method("get")
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
	// 		  $this->userService, $this->trashManager , $this->shareManager,
	// $this->groupManager, $this->cloudFederationProviderManager,
	// $this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
	// 	);
	// 	$result = $controller->Move($this->userId);
	// 	$this->assertEquals($result->getData(),"OK");
	// }

	public function testRemoveGrant() {
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->RemoveGrant($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testRestoreRecycleItem() {
		// we are using original location as the RecycleItem's
		// unique key string, see:
		// https://github.com/cs3org/cs3apis/blob/6eab4643f5113a54f4ce4cd8cb462685d0cdd2ef/cs3/storage/provider/v1beta1/resources.proto#L318
		$this->request->method("getParam")
			->with($this->equalTo("key"))
		  ->willReturn("/some/key/that/is/really/a/path.txt");

		// we don't need to look at getParam("path") because reva will always just
		// put the user's home dir there, see https://github.com/cs3org/reva/pull/2120

		// we don't look at getParam("restoreRef")
		// because the nextcloud trash manager doesn't support restoring
		// to a different location.
		$user = $this->getMockBuilder("OCP\IUser")->getMock();
		$this->userManager->method("get")->willReturn($user);
		$item1 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item1->method("getOriginalLocation")
			->willReturn("something/unrelated/to/science.mesh");
		$item2 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item2->method("getOriginalLocation")
			->willReturn("sciencemesh/somethingElse/file2.json");
		$item3 = $this->getMockBuilder("OCA\Files_Trashbin\Trash\ITrashItem")->getMock();
		$item3->method("getOriginalLocation")
			->willReturn("sciencemesh/some/key/that/is/really/a/path.txt");

		$trashItems = [
			$item1,
			$item2,
			$item3,
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
			  $this->userService, $this->trashManager , $this->shareManager,
				$this->groupManager, $this->cloudFederationProviderManager,
				$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
			);

		$result = $controller->RestoreRecycleItem($this->userId);
		$this->assertEquals($result->getData(),"OK");
	}

	public function testRestoretRevision() {
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->RestoreRevision($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testSetArbitraryMetadatan() {
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->SetArbitraryMetadata($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testUnsetArbitraryMetadata() {
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->UnsetArbitraryMetadata($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testUpdateGrant() {
		$this->request->method("getParam")->willReturn("/test.json");
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$result = $controller->UpdateGrant($this->userId);
		$this->assertEquals($result->getData(),"Not implemented");
	}

	public function testUpload() {
		$testFile = $this->getMockBuilder("OCP\Files\File")->getMock();
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/test.json"))
			->willReturn($testFile);
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
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$testFile->expects($this->once())
			->method('putContent')
			->with($this->equalTo("some-content"));
		;

		$this->userFolder->method("getPath")
			->willReturn("");
		$result = $controller->Upload($this->userId, "/test.json");
		$this->assertEquals($result->getData(),"OK");
	}

	public function testAddSentShare() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$testCreatedShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$testLockedNode = $this->getMockBuilder("OCP\Files\Node")->getMock();
		$storage = $this->getMockBuilder("\OC\Files\Storage\Storage")->getMock();
		$paramsMap = [
			["md", null,["opaque_id" => "fileid-marie%2FtestFile.json"]],
			["g", null,["grantee" => ["Id" => ["UserId" => ["idp" => "localhost:8080","opaque_id" => "einstein","type" => 1]]],"permissions" => ["permissions" => ["get_path" => true]]]]
		];
		$this->request->method("getParam")
			->will($this->returnValueMap($paramsMap));
		$this->shareManager->method("newShare")
			->willReturn($testShare);
		$testShare->method("getNode")
			->willReturn($testLockedNode);
		$this->userFolder->method("get")
			->willReturn($testFolder);
		$this->shareManager->method("shareApiAllowLinks")
			->willReturn(true);
		$this->shareManager->method("shareApiLinkAllowPublicUpload")
			->willReturn(true);
		$this->appManager->method("isEnabledForUser")
			->willReturn(true);
		$this->shareManager->method("createShare")
			->willReturn($testCreatedShare);
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/testFile.json"))
			->willReturn($testFolder);
		$testFolder->method("getStorage")
			->willReturn($storage);

		$response = [
			"id" => [
				"map" => null,
			],
			"resource_id" => [
				"map" => null,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => true,
					"create_container" => true,
					"delete" => true,
					"get_path" => true,
					"get_quota" => true,
					"initiate_file_download" => true,
					"initiate_file_upload" => true,
					"list_grants" => true,
					"list_container" => true,
					"list_file_versions" => true,
					"list_recycle" => true,
					"move" => true,
					"remove_grant" => true,
					"purge_recycle" => true,
					"restore_file_version" => true,
					"restore_recycle_item" => true,
					"stat" => true,
					"update_grant" => true,
					"deny_grant" => true
				]
			],
			"grantee" => [
				"Id" => [
					"UserId" => [
						"idp" => "0.0.0.0:19000",
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => "0::.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => "0.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			]
		];
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();

		$result = $controller->addSentShare($this->userId);
		$this->assertEquals($result->getData(),$response);
		$this->assertEquals($result->getStatus(),201);
	}

	public function testAddReceivedShare() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
			$this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$cloudId = $this->getMockBuilder("OCP\Federation\ICloudId")->getMock();
		$provider = $this->getMockBuilder("OCP\Federation\ICloudFederationProvider")->getMock();
		$share = $this->getMockBuilder("OCP\Federation\ICloudFederationShare")->getMock();
		$user = $this->getMockBuilder("OCP\IUser")->getMock();

		$paramsMap = [
			["md",null,["opaque_id" => "fileid-einstein%2Fmy-folder"]],
			["g",null,["grantee" => ["type" => 1,"Id" => ["UserId" => ["idp" => "cesnet.cz","opaque_id" => "marie","type" => 1]]]]],
			["provider_domain",null,"cern.ch"],
			["resource_type",null,"file"],
			["provider_id",null,2],
			["owner_display_name",null,"Albert Einstein"],
			["protocol",null,["name" => "webdav","options" => ["sharedSecret" => "secret","permissions" => "webdav-property"]]]
		];
		$this->request->method("getParam")
			->will($this->returnValueMap($paramsMap));
		$this->cloudIdManager->method("resolveCloudId")
			->willReturn($cloudId);
		$cloudId->method("getUser")
			->willReturn("marie");
		$this->userManager->method("userExists")
			->willReturn(true);
		$this->urlGenerator->method("getBaseUrl")
			->willReturn("welcome server2.txt");
		$this->cloudFederationProviderManager->method("getCloudFederationProvider")
			->willReturn($provider);
		$this->factory->method("getCloudFederationShare")
			->willReturn($share);
		$this->userManager->method("get")
			->willReturn($user);


		$result = $controller->addReceivedShare($this->userId);
		$response = '{"id":{},"resource_id":{},"permissions":{"permissions":{"add_grant":true,"create_container":true,"delete":true,"get_path":true,"get_quota":true,"initiate_file_download":true,"initiate_file_upload":true,"list_grants":true,"list_container":true,"list_file_versions":true,"list_recycle":true,"move":true,"remove_grant":true,"purge_recycle":true,"restore_file_version":true,"restore_recycle_item":true,"stat":true,"update_grant":true,"deny_grant":true}},"grantee":{"Id":{"UserId":{"idp":"0.0.0.0:19000","opaque_id":"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c","type":1}}},"owner":{"idp":"0.0.0.0:19000","opaque_id":"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c","type":1},"creator":{"idp":"0.0.0.0:19000","opaque_id":"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c","type":1},"ctime":{"seconds":1234567890},"mtime":{"seconds":1234567890}}';

		$this->assertEquals($result->getData(),json_decode($response));
		$this->assertEquals($result->getStatus(),201);
	}
	public function testGetShare() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();
		$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$testCreatedShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$testLockedNode = $this->getMockBuilder("OCP\Files\Node")->getMock();
		$storage = $this->getMockBuilder("\OC\Files\Storage\Storage")->getMock();
		$paramsMap = [
			["md", null,["opaque_id" => "fileid-marie%2FtestFile.json"]],
			["g", null,["grantee" => ["Id" => ["UserId" => ["idp" => "localhost:8080","opaque_id" => "einstein","type" => 1]]],"permissions" => ["permissions" => ["get_path" => true]]]]
		];
		$this->request->method("getParam")
			->will($this->returnValueMap($paramsMap));
		$this->shareManager->method("newShare")
			->willReturn($testShare);
		$testShare->method("getNode")
			->willReturn($testLockedNode);
		$this->userFolder->method("get")
			->willReturn($testFolder);
		$this->shareManager->method("shareApiAllowLinks")
			->willReturn(true);
		$this->shareManager->method("shareApiLinkAllowPublicUpload")
			->willReturn(true);
		$this->appManager->method("isEnabledForUser")
			->willReturn(true);
		$this->shareManager->method("createShare")
			->willReturn($testCreatedShare);
		$this->userFolder->method("get")
			->with($this->equalTo("sciencemesh/testFile.json"))
			->willReturn($testFolder);
		$testFolder->method("getStorage")
			->willReturn($storage);

		$response = [
			"id" => [
				"map" => null,
			],
			"resource_id" => [
				"map" => null,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => true,
					"create_container" => true,
					"delete" => true,
					"get_path" => true,
					"get_quota" => true,
					"initiate_file_download" => true,
					"initiate_file_upload" => true,
					"list_grants" => true,
					"list_container" => true,
					"list_file_versions" => true,
					"list_recycle" => true,
					"move" => true,
					"remove_grant" => true,
					"purge_recycle" => true,
					"restore_file_version" => true,
					"restore_recycle_item" => true,
					"stat" => true,
					"update_grant" => true,
					"deny_grant" => true
				]
			],
			"grantee" => [
				"Id" => [
					"UserId" => [
						"idp" => "0.0.0.0:19000",
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => "0::.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => "0.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			]
		];
		$testFolder = $this->getMockBuilder("OCP\Files\Folder")->getMock();

		$result = $controller->addSentShare($this->userId);
		$this->assertEquals($result->getData(),$response);
		$this->assertEquals($result->getStatus(),201);
	}

	// public function testUnshare(){
	// 	$controller = new RevaController(
	// 		$this->appName, $this->rootFolder, $this->request, $this->session,
	// 		$this->userManager, $this->urlGenerator, $this->userId, $this->config,
	// 	  $this->userService, $this->trashManager , $this->shareManager,
	// 		$this->groupManager, $this->cloudFederationProviderManager,
	// 		$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
	// 	);
	// 	$this->request->method("getParam")
	// 		->willReturn(
	// 			[
	// 			"Id"=>[
	// 				"opaque_id"=>"some-share-id"
	// 				]
	// 			]
	// 			);
	// 	$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
	// 	$this->shareManager->method("getShareById")
	// 		->willReturn($testShare);
	// 	$this->shareManager->method("deleteShare")
	// 		->willReturn(true);
	// 	$result = $controller->Unshare($this->userId);
	// 	$this->assertEquals($result->getData(),"OK");
	// 	$this->assertEquals($result->getStatus(),200);
	// }

	public function testUpdateShare() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
		$this->groupManager, $this->cloudFederationProviderManager,
		$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$testShareUpdated = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$paramsMap = [
			["ref", null,["Spec" => ["Id" => ["opaque_id" => "some-share-id"]]]],
			["p", null,	["permissions" => ["add_grant" => true,"create_container" => true,"delete" => true,"get_path" => true,"get_quota" => true,"initiate_file_download" => true,"initiate_file_upload" => true,"list_grants" => true,"list_container" => true,"list_file_versions" => true,"list_recycle" => true,"move" => true,"remove_grant" => true,"purge_recycle" => true,"restore_file_version" => true,"restore_recycle_item" => true,	"stat" => true,"update_grant" => true,"deny_grant" => true]]]
		];
		$this->request->method("getParam")
			->will($this->returnValueMap($paramsMap));
		$this->shareManager->method("getShareById")
			->willReturn($testShare);
		$this->shareManager->method("updateShare")
			->willReturn($testShareUpdated);
		$response = [
			"id" => [
				"map" => null,
			],
			"resource_id" => [
				"map" => null,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => true,
					"create_container" => true,
					"delete" => true,
					"get_path" => true,
					"get_quota" => true,
					"initiate_file_download" => true,
					"initiate_file_upload" => true,
					"list_grants" => true,
					"list_container" => true,
					"list_file_versions" => true,
					"list_recycle" => true,
					"move" => true,
					"remove_grant" => true,
					"purge_recycle" => true,
					"restore_file_version" => true,
					"restore_recycle_item" => true,
					"stat" => true,
					"update_grant" => true,
					"deny_grant" => true
				]
			],
			"grantee" => [
				"Id" => [
					"UserId" => [
						"idp" => "0.0.0.0:19000",
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => "0::.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => "0.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			]
		];
		$result = $controller->UpdateShare($this->userId);
		$this->assertEquals($result->getData(),$response);
		$this->assertEquals($result->getStatus(),200);
	}
	public function testListShares() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
		$this->groupManager, $this->cloudFederationProviderManager,
		$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,

		);
		$this->request->method("getParams")
			->willReturn(
				[
					"POST",
					"/apps/sciencemesh/~tester/api/share/ListShares",
					[
						"type" => 4,
						"Term" => [
							"Creator" => [
								"idp" => "0.0.0.0=>19000",
								"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
								"type" => 1
							]
						]
					]
				]
			);
		$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$this->shareManager->method("getSharesBy")
			->willReturn([$testShare]);
		$result = $controller->ListShares($this->userId);
		$responses = [[
			"id" => [
				"map" => null,
			],
			"resource_id" => [
				"map" => null,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => true,
					"create_container" => true,
					"delete" => true,
					"get_path" => true,
					"get_quota" => true,
					"initiate_file_download" => true,
					"initiate_file_upload" => true,
					"list_grants" => true,
					"list_container" => true,
					"list_file_versions" => true,
					"list_recycle" => true,
					"move" => true,
					"remove_grant" => true,
					"purge_recycle" => true,
					"restore_file_version" => true,
					"restore_recycle_item" => true,
					"stat" => true,
					"update_grant" => true,
					"deny_grant" => true
				]
			],
			"grantee" => [
				"Id" => [
					"UserId" => [
						"idp" => "0.0.0.0:19000",
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => "0::.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => "0.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			]
		]];
		$this->assertEquals($result->getData(),$responses);
		$this->assertEquals($result->getStatus(),200);
	}
	public function testListSharesEmpty() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
		$this->groupManager, $this->cloudFederationProviderManager,
		$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$this->request->method("getParams")
			->willReturn(
				[
					"POST",
					"/apps/sciencemesh/~tester/api/share/ListShares",
					[
						"type" => 4,
						"Term" => [
							"Creator" => [
								"idp" => "0.0.0.0=>19000",
								"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
								"type" => 1
							]
						]
					]
				]
			);
		$this->shareManager->method("getSharesBy")
			->willReturn([]);
		$result = $controller->ListShares($this->userId);
		$this->assertEquals($result->getData(),[]);
		$this->assertEquals($result->getStatus(),200);
	}
	public function testListReceivedShares() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
		$this->groupManager, $this->cloudFederationProviderManager,
		$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$this->request->method("getParams")
			->willReturn(
				[
					"POST",
					"/apps/sciencemesh/~tester/api/share/ListShares",
					[
						"type" => 4,
						"Term" => [
							"Creator" => [
								"idp" => "0.0.0.0=>19000",
								"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
								"type" => 1
							]
						]
					]
				]
			);
		$this->shareProvider->method("getExternalShares")
			->willReturn([$testShare]);
		$responses = [[
			"id" => [
				"map" => null,
			],
			"resource_id" => [
				"map" => null,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => true,
					"create_container" => true,
					"delete" => true,
					"get_path" => true,
					"get_quota" => true,
					"initiate_file_download" => true,
					"initiate_file_upload" => true,
					"list_grants" => true,
					"list_container" => true,
					"list_file_versions" => true,
					"list_recycle" => true,
					"move" => true,
					"remove_grant" => true,
					"purge_recycle" => true,
					"restore_file_version" => true,
					"restore_recycle_item" => true,
					"stat" => true,
					"update_grant" => true,
					"deny_grant" => true
				]
			],
			"grantee" => [
				"Id" => [
					"UserId" => [
						"idp" => "0.0.0.0:19000",
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => "0::.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => "0.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			],
			"state" => 2
		]];
		$result = $controller->ListReceivedShares($this->userId);
		$this->assertEquals($result->getData(),$responses);
		$this->assertEquals($result->getStatus(),200);
	}
	public function testListReceivedSharesEmpty() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
		$this->groupManager, $this->cloudFederationProviderManager,
		$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$this->request->method("getParams")
			->willReturn(
				[
					"POST",
					"/apps/sciencemesh/~tester/api/share/ListShares",
					[
						"type" => 4,
						"Term" => [
							"Creator" => [
								"idp" => "0.0.0.0=>19000",
								"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
								"type" => 1
							]
						]
					]
				]
			);
		$this->shareProvider->method("getExternalShares")
			->willReturn([]);
		$result = $controller->ListReceivedShares($this->userId);

		$this->assertEquals($result->getData(),[]);
		$this->assertEquals($result->getStatus(),200);
	}
	public function testGetReceivedShare() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
			$this->groupManager, $this->cloudFederationProviderManager,
			$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$this->request->method("getParam")
			->willReturn(
				[
					"Id" => [
						"opaque_id" => "some-share-id"
					]
				]
				);
		$this->shareManager->method("getShareById")
			->willReturn($testShare);
		$response = [
			"id" => [
				"map" => null,
			],
			"resource_id" => [
				"map" => null,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => true,
					"create_container" => true,
					"delete" => true,
					"get_path" => true,
					"get_quota" => true,
					"initiate_file_download" => true,
					"initiate_file_upload" => true,
					"list_grants" => true,
					"list_container" => true,
					"list_file_versions" => true,
					"list_recycle" => true,
					"move" => true,
					"remove_grant" => true,
					"purge_recycle" => true,
					"restore_file_version" => true,
					"restore_recycle_item" => true,
					"stat" => true,
					"update_grant" => true,
					"deny_grant" => true
				]
			],
			"grantee" => [
				"Id" => [
					"UserId" => [
						"idp" => "0.0.0.0:19000",
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => "0::.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => "0.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			],
			"state" => 2
		];
		$result = $controller->GetReceivedShare($this->userId);
		$this->assertEquals($result->getData(),$response);
		$this->assertEquals($result->getStatus(),200);
	}
	public function testUpdateReceivedShare() {
		$controller = new RevaController(
			$this->appName, $this->rootFolder, $this->request, $this->session,
			$this->userManager, $this->urlGenerator, $this->userId, $this->config,
		  $this->userService, $this->trashManager , $this->shareManager,
		$this->groupManager, $this->cloudFederationProviderManager,
		$this->factory, $this->cloudIdManager,$this->logger,$this->appManager, $this->l, $this->shareProvider,
		);
		$testShare = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$testShareUpdated = $this->getMockBuilder("OCP\Share\IShare")->getMock();
		$paramsMap = [
			["ref", null,["Spec" => ["Id" => ["opaque_id" => "some-share-id"]]]],
			["p", null,	["permissions" => ["add_grant" => true,"create_container" => true,"delete" => true,"get_path" => true,"get_quota" => true,"initiate_file_download" => true,"initiate_file_upload" => true,"list_grants" => true,"list_container" => true,"list_file_versions" => true,"list_recycle" => true,"move" => true,"remove_grant" => true,"purge_recycle" => true,"restore_file_version" => true,"restore_recycle_item" => true,	"stat" => true,"update_grant" => true,"deny_grant" => true]]]
		];
		$this->request->method("getParam")
			->will($this->returnValueMap($paramsMap));
		$this->shareManager->method("getShareById")
			->willReturn($testShare);
		$this->shareManager->method("updateShare")
			->willReturn($testShareUpdated);
		$response = [
			"id" => [
				"map" => null,
			],
			"resource_id" => [
				"map" => null,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => true,
					"create_container" => true,
					"delete" => true,
					"get_path" => true,
					"get_quota" => true,
					"initiate_file_download" => true,
					"initiate_file_upload" => true,
					"list_grants" => true,
					"list_container" => true,
					"list_file_versions" => true,
					"list_recycle" => true,
					"move" => true,
					"remove_grant" => true,
					"purge_recycle" => true,
					"restore_file_version" => true,
					"restore_recycle_item" => true,
					"stat" => true,
					"update_grant" => true,
					"deny_grant" => true
				]
			],
			"grantee" => [
				"Id" => [
					"UserId" => [
						"idp" => "0.0.0.0:19000",
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => "0::.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => "0.0.0.0:19000",
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			],
			"state" => 2
		];
		$result = $controller->UpdateReceivedShare($this->userId);
		$this->assertEquals($result->getData(),$response);
		$this->assertEquals($result->getStatus(),200);
	}
}
