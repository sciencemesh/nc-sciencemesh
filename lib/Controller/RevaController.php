<?php

namespace OCA\ScienceMesh\Controller;

use OCA\ScienceMesh\NextcloudAdapter;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCA\ScienceMesh\Share\ScienceMeshSharePermissions;
use OCA\ScienceMesh\User\ScienceMeshUserId;

use OCA\Files_Trashbin\Trash\ITrashManager;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\IConfig;

use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use \OCP\Files\NotFoundException;
use League\Flysystem\FileNotFoundException;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\OCS\OCSNotFoundException;

use OCA\CloudFederationAPI\Config;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;

use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Share\Exceptions\ShareNotFound;

use Psr\Log\LoggerInterface;

use OCP\App\IAppManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\IL10N;

class RevaController extends Controller {


	/* @var ISession */
	private $session;

	/** @var LoggerInterface */
	private $logger;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ICloudFederationProviderManager */
	private $cloudFederationProviderManager;

	/** @var Config */
	private $config;

	/** @var ICloudFederationFactory */
	private $factory;

	/** @var ICloudIdManager */
	private $cloudIdManager;

	/** @var \OCP\Files\Node */
	private $lockedNode;

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l;

	public function __construct(
		$AppName,
		IRootFolder $rootFolder,
		IRequest $request,
		ISession $session,
		IUserManager $userManager,
		IURLGenerator $urlGenerator,
		$userId,
		IConfig $config,
		\OCA\ScienceMesh\Service\UserService $UserService,
		ITrashManager $trashManager,
		IManager $shareManager,
		IGroupManager $groupManager,
		ICloudFederationProviderManager $cloudFederationProviderManager,
		ICloudFederationFactory $factory,
		ICloudIdManager $cloudIdManager,
		LoggerInterface $logger,
		IAppManager $appManager,
		IL10N $l10n,
		ScienceMeshShareProvider $shareProvider
	) {
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');

		$this->rootFolder = $rootFolder;
		$this->request = $request;
		$this->session = $session;
		$this->userManager = $userManager;
		$this->urlGenerator = $urlGenerator;

		$this->config = new \OCA\ScienceMesh\ServerConfig($config);

		$this->trashManager = $trashManager;
		$this->shareManager = $shareManager;
		$this->groupManager = $groupManager;
		$this->cloudFederationProviderManager = $cloudFederationProviderManager;
		$this->factory = $factory;
		$this->cloudIdManager = $cloudIdManager;
		$this->logger = $logger;
		$this->appManager = $appManager;
		$this->l = $l10n;
		$this->shareProvider = $shareProvider;
	}
	private function init($userId) {
		$this->checkRevadAuth();
		error_log("in controller init, getting user folder for $userId");
		$this->userFolder = $this->rootFolder->getUserFolder($userId);
		// Create the Nextcloud Adapter
		$adapter = new NextcloudAdapter($this->userFolder);
		$this->filesystem = new \League\Flysystem\Filesystem($adapter);
		$this->baseUrl = $this->getStorageUrl($userId); // Where is that used?
	}

	/**
	 * @param array $nodeInfo
	 *
	 * Returns the data of a CS3 provider.ResourceInfo object https://github.com/cs3org/cs3apis/blob/a86e5cb/cs3/storage/provider/v1beta1/resources.proto#L35-L93
	 * @return array
	 *
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function lock(\OCP\Files\Node $node) {
		$node->lock(ILockingProvider::LOCK_SHARED);
		$this->lockedNode = $node;
	}

	/**
	 * Make sure that the passed date is valid ISO 8601
	 * So YYYY-MM-DD
	 * If not throw an exception
	 *
	 * @param string $expireDate
	 *
	 * @throws \Exception
	 * @return \DateTime
	 */
	private function parseDate(string $expireDate): \DateTime {
		try {
			$date = new \DateTime($expireDate);
		} catch (\Exception $e) {
			throw new \Exception('Invalid date. Format must be YYYY-MM-DD');
		}

		$date->setTime(0, 0, 0);

		return $date;
	}
	private function checkRevadAuth() {
		$authHeader = $this->request->getHeader('X-Reva-Secret');
		error_log("Auth header $authHeader");
    if ($authHeader != $this->config->getRevaSharedSecret()) {
		  throw new \OCP\Files\NotPermittedException('Please set an http request header "X-Reva-Secret: <your_shared_secret>"!');
		}
	}
	private function getSharedByOpaqueId($opaqueId) {
		$opaqueIdDecoded = urldecode($opaqueId);
		$opaqueIdExploded = explode("/",$opaqueIdDecoded);
		$sharedBy = substr($opaqueIdExploded[0], strlen("fileid-"));
		return $sharedBy;
	}
	/*
	* @throws OCSNotFoundException
	*/
	private function getNameByOpaqueId($opaqueId) {
		$opaqueIdDecoded = urldecode($opaqueId);
		$opaqueIdExploded = explode("/",$opaqueIdDecoded);
		//$name resource name (e.g. document.odt)
		$name = end($opaqueIdExploded);
		if ($name) {
			return $name;
		}
		return false;
	}

	private function nodeInfoToCS3ResourceInfo(array $nodeInfo) : array {
		$path = substr($nodeInfo["path"], strlen("/sciencemesh"));
		$isDirectory = ($nodeInfo["mimetype"] == "directory");
		return [
			"opaque" => [
				"map" => null,
			],
			"type" => ($isDirectory ? 2 : 1),
			"id" => [
				"opaque_id" => "fileid-/" . $path,
			],
			"checksum" => [
				"type" => 0,
				"sum" => "",
			],
			"etag" => "deadbeef",
			"mime_type" => $nodeInfo["mimetype"],
			"mtime" => [
				"seconds" => 1234567890
			],
			"path" => "/" . $path,
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
	}

	# For ListReceivedShares, GetReceivedShare and UpdateReceivedShare we need to include "state:2"
	private function shareInfoToResourceInfo(IShare $share): array {
		return [
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
						"idp" => $this->config->getIopUrl(),
						"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type" => 1
					]
				]
			],
			"owner" => [
				"idp" => $this->config->getIopUrl(),
				"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type" => 1
			],
			"creator" => [
				"idp" => $this->config->getIopUrl(),
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
	}

	# correspondes the permissions we got from Reva to Nextcloud
	private function getPermissionsCode(array $permissions) : int {
		$permissionsCode = 0;
		if (!empty($permissions["get_path"]) || !empty($permissions["get_quota"]) || !empty($permissions["initiate_file_download"]) || !empty($permissions["initiate_file_upload"]) || !empty($permissions["stat"])) {
			$permissionsCode += \OCP\Constants::PERMISSION_READ;
		}
		if (!empty($permissions["create_container"]) || !empty($permissions["move"]) || !empty($permissions["add_grant"]) || !empty($permissions["restore_file_version"]) || !empty($permissions["restore_recycle_item"])) {
			$permissionsCode += \OCP\Constants::PERMISSION_CREATE;
		}
		if (!empty($permissions["move"]) || !empty($permissions["delete"]) || !empty($permissions["remove_grant"])) {
			$permissionsCode += \OCP\Constants::PERMISSION_DELETE;
		}
		if (!empty($permissions["list_grants"]) || !empty($permissions["list_file_versions"]) || !empty($permissions["list_recycle"])) {
			$permissionsCode += \OCP\Constants::PERMISSION_SHARE;
		}
		if (!empty($permissions["update_grant"])) {
			$permissionsCode += \OCP\Constants::PERMISSION_UPDATE;
		}
		return $permissionsCode;
	}
	/**
	 * @param int
	 *
	 * @return int
	 * @throws NotFoundException
	 */
	private function getStorageUrl($userId) {
		$storageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("sciencemesh.storage.handleHead", ["userId" => $userId, "path" => "foo"]));
		$storageUrl = preg_replace('/foo$/', '', $storageUrl);
		return $storageUrl;
	}

	/* Reva handlers */

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function AddGrant($userId) {
		$this->init($userId);
		
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a param with a grant to add here;
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	private function formatUser($user) {
		return [
			"id" => [
				"idp" => $this->config->getIopUrl(),
				"opaque_id" => $user->getUID(),
			],
			"display_name" => $user->getDisplayName(),
			"email" => $user->getEmailAddress(),
			"type" => 1,
		];
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function Authenticate($userId) {
		$this->init($userId);
		$userId = $this->request->getParam("clientID");
		$password = $this->request->getParam("clientSecret");

		// Try e.g.:
		// curl -v -H 'Content-Type:application/json' -d'{"clientID":"einstein",clientSecret":"relativity"}' http://einstein:relativity@localhost/index.php/apps/sciencemesh/~einstein/api/auth/Authenticate

    // Ref https://github.com/cs3org/reva/issues/2356
		if ($password == $this->config->getRevaLoopbackSecret()) {
			error_log("user " . $userId . " and loopback secret!");
			$user = $this->userManager->get($userId);
		} else {
				$user = $this->userManager->checkPassword($userId, $password);
		}
		if ($user) {
			$result = [
				"user" => $this->formatUser($user),
				"scopes" => [
					"user" => [
						"resource" => [
							"decoder" => "json",
							"value" => "eyJyZXNvdXJjZV9pZCI6eyJzdG9yYWdlX2lkIjoic3RvcmFnZS1pZCIsIm9wYXF1ZV9pZCI6Im9wYXF1ZS1pZCJ9LCJwYXRoIjoic29tZS9maWxlL3BhdGgudHh0In0=",
						],
						"role" => 1,
					],
				],
			];
			return new JSONResponse($result, Http::STATUS_OK);
		}
		return new JSONResponse("Username / password not recognized", 401);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function CreateDir($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path"); // FIXME: sanitize the input
		try {
			$this->filesystem->createDir($path);
		} catch (NotPermittedException $e) {
			return new JSONResponse(["error" => "Could not create directory."], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new JSONResponse("OK", Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function CreateHome($userId) {
		error_log("CreateHome $userId");
		$this->init($userId);
		error_log('CreateHome inited');
		$homeExists = $this->userFolder->nodeExists("sciencemesh");
		if (!$homeExists) {
			error_log('CreateHome home does not exist');
			try {
				$this->userFolder->newFolder("sciencemesh"); // Create the Sciencemesh directory for storage if it doesn't exist.
			} catch (NotPermittedException $e) {
				return new JSONResponse(["error" => "Create home failed. Resource Path not foun"], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			return new JSONResponse("CREATED", Http::STATUS_CREATED);
		}
		error_log('CreateHome nothing to do');
		return new JSONResponse("OK", Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function CreateReference($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: normalize incoming path
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function CreateStorageSpace($userId) {
		return new JSONResponse([
			"status" => [
				"code" => 1,
				"trace" => "00000000000000000000000000000000"
			],
			"storage_space" => [
				"opaque" => [
					"map" => [
						"bar" => [
							"value" => "c2FtYQ=="
						],
						"foo" => [
							"value" => "c2FtYQ=="
						]
					]
				],
				"id" => [
					"opaque_id" => "some-opaque-storage-space-id"
				],
				"owner" => [
					"id" => [
						"idp" => "some-idp",
						"opaque_id" => "some-opaque-user-id",
						"type" => 1
					]
				],
				"root" => [
					"storage_id" => "some-storage-id",
					"opaque_id" => "some-opaque-root-id"
				],
				"name" => "My Storage Space",
				"quota" => [
					"quota_max_bytes" => 456,
					"quota_max_files" => 123
				],
				"space_type" => "home",
				"mtime" => [
					"seconds" => 1234567890
				]
			]
		], Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 * @throws FileNotFoundException
	 */
	public function Delete($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: normalize incoming path
		try {
			$this->filesystem->delete($path);
			return new JSONResponse("OK", Http::STATUS_OK);
		} catch (FileNotFoundException $e) {
			return new JSONResponse(["error" => "Failed to delete."], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function EmptyRecycle($userId) {
		$this->init($userId);
		$user = $this->userManager->get($userId);
		$trashItems = $this->trashManager->listTrashRoot($user);

		$result = []; // Where is this used?
		foreach ($trashItems as $node) {
			#getOriginalLocation : returns string
			if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
				$this->trashManager->removeItem($node);
			}
		}
		return new JSONResponse("OK", Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function GetMD($userId) {
		$this->init($userId);
		$ref = $this->request->getParam("ref");
		$path = "sciencemesh" . $ref["path"]; // FIXME: normalize incoming path
		$success = $this->filesystem->has($path);
		if ($success) {
			$nodeInfo = $this->filesystem->getMetaData($path);
			$resourceInfo = $this->nodeInfoToCS3ResourceInfo($nodeInfo);
			return new JSONResponse($resourceInfo, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "File not found"], 404);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function GetPathByID($userId) {
		$this->init($userId);
		// in progress
		$path = "subdir/";
		$storageId = $this->request->getParam("storage_id");
		$opaqueId = $this->request->getParam("opaque_id");
		return new TextPlainResponse($path, Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function InitiateUpload($userId) {
		$this->init($userId);
		$response = [
			"simple" => "yes",
			"tus" => "yes" // FIXME: Not really supporting this;
		];
		return new JSONResponse($response, Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function ListFolder($userId) {
		$this->init($userId);
		$ref = $this->request->getParam("ref");
		$path = "sciencemesh" . $ref["path"]; // FIXME: sanitize!
		$success = $this->filesystem->has($path);
		if (!$success) {
			return new JSONResponse(["error" => "Folder not found"], 404);
		}
		$nodeInfos = $this->filesystem->listContents($path);
		$resourceInfos = array_map(function ($nodeInfo) {
			return $this->nodeInfoToCS3ResourceInfo($nodeInfo);
		}, $nodeInfos);
		return new JSONResponse($resourceInfos, Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function ListGrants($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented",Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function ListRecycle($userId) {
		$this->init($userId);
		$user = $this->userManager->get($userId);
		$trashItems = $this->trashManager->listTrashRoot($user);
		$result = [];
		foreach ($trashItems as $node) {
			if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
				$path = substr($node->getOriginalLocation(), strlen("sciencemesh"));
				$result = [
					[
						"opaque" => [
							"map" => null,
						],
						"key" => $path,
						"ref" => [
							"resource_id" => [
								"map" => null,
							],
							"path" => $path,
						],
						"size" => 12345,
						"deletion_time" => [
							"seconds" => 1234567890
						]
					]];
			}
		}
		return new JSONResponse($result, Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function ListRevisions($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented",Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	// public function Move($userId) {
	// 	$from = $this->request->getParam("from");
	// 	$to = $this->request->getParam("to");
	// 	$success = $this->filesystem->move($from, $to);
	// 	if ($success) {
	// 		return new JSONResponse("OK", Http::STATUS_OK);
	// 	}
	// 	return new JSONResponse(["error" => "Failed to move."], Http::STATUS_INTERNAL_SERVER_ERROR);
	// }

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function RemoveGrant($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a grant to remove here;
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function RestoreRecycleItem($userId) {
		$this->init($userId);
		$key = $this->request->getParam("key");
		$user = $this->userManager->get($userId);
		$trashItems = $this->trashManager->listTrashRoot($user);

		foreach ($trashItems as $node) {
			if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
				// we are using original location as the RecycleItem's
				// unique key string, see:
				// https://github.com/cs3org/cs3apis/blob/6eab4643f5113a54f4ce4cd8cb462685d0cdd2ef/cs3/storage/provider/v1beta1/resources.proto#L318

				if ("sciencemesh" . $key == $node->getOriginalLocation()) {
					$this->trashManager->restoreItem($node);
					return new JSONResponse("OK", Http::STATUS_OK);
				}
			}
		}
		return new JSONResponse('["error" => "Not found."]', 404);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function RestoreRevision($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a revision param here;
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function SetArbitraryMetadata($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		$metadata = $this->request->getParam("metadata");
		// FIXME: What do we do with the existing metadata? Just toss it and overwrite with the new value? Or do we merge?
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function UnsetArbitraryMetadata($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function UpdateGrant($userId) {
		$this->init($userId);
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a paramater with the grant(s)
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function Upload($userId, $path) {
		$this->init($userId);
		$contents = $this->request->put;
		if ($this->filesystem->has("/sciencemesh" . $path)) {
			if ($this->filesystem->update("/sciencemesh" . $path, $contents)) {
				return new JSONResponse("OK", Http::STATUS_OK);
			}
			return new JSONResponse(["error" => "Update failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($this->filesystem->write("/sciencemesh" . $path, $contents)) {
			return new JSONResponse("CREATED", Http::STATUS_CREATED);
		}
		return new JSONResponse(["error" => "Create failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * Get user list.
	 */
	public function GetUser($userId) {
		$this->init($userId);
		$userToCheck = $this->request->getParam('opaque_id');
		if ($this->userManager->userExists($userToCheck)) {
			$user = $this->userManager->get($userToCheck);
			$response = $this->formatUser($user);
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(
			['message' => 'User does not exist'],
			Http::STATUS_NOT_FOUND
		);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 * @throws NotFoundException
	 * @throws OCSNotFoundException
	 * Create a new share in fn with the given access control list.
	 */
	public function addSentShare($userId) {
		$this->init($userId);
		$granteeIdUserId = $this->request->getParam("g")["grantee"]["Id"]["UserId"];
		$opaqueId = $this->request->getParam("md")["opaque_id"];
		$granteeIdUserId = $this->request->getParam("g")["grantee"]["Id"]["UserId"];
		$permissions = $this->getPermissionsCode($this->request->getParam("g")["permissions"]["permissions"]);
		$name = $this->getNameByOpaqueId($opaqueId);
		$shareWith = $granteeIdUserId["opaque_id"]."@".$granteeIdUserId["idp"];
		if (
			!isset($granteeIdUserId) ||
			!isset($opaqueId) ||
			!isset($granteeIdUserId) ||
			!isset($permissions) ||
			!isset($name) ||
			!isset($shareWith)
		) {
			return new JSONResponse(
				['message' => 'Missing arguments'],Http::STATUS_BAD_REQUEST
			);
		}
		try {
			$path = $this->userFolder->get("sciencemesh/".$name);
		} catch (NotFoundException $e) {
			return new JSONResponse(["error" => "Share failed. Resource Path not found"], Http::STATUS_BAD_REQUEST);
		}
		if ($this->shareProvider->getSentShareByName($userId,$name)) {
			return new JSONResponse(["Already sent this share"], Http::STATUS_ACCEPTED);
		}
		$share = $this->shareManager->newShare();
		$share->setNode($path);
		try {
			$this->lock($share->getNode());
		} catch (LockedException $e) {
			throw new OCSNotFoundException($this->l->t('Could not create share'));
		}
		$share->setShareType(14);//IShare::TYPE_SCIENCEMESH);
		$share->setSharedBy($userId);
		$share->setSharedWith($shareWith);
		$share->setShareOwner($userId);
		$share->setPermissions($permissions);
		$this->shareProvider->create($share);
		$response = $this->shareInfoToResourceInfo($share);
		return new JSONResponse($response, Http::STATUS_CREATED);
	}
	/**
	 * add a received share
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 * @return Http\DataResponse|JSONResponse
	 */
	public function addReceivedShare($userId) {
		$this->init($userId);
		$providerDomain = $this->request->getParam("provider_domain");
		$providerId = $this->request->getParam("provider_id");
		$opaqueId = $this->request->getParam("md")["opaque_id"];
		$resourceId = $this->request->getParam("md")["resource_id"] ?? '';
		$permissionJson = $this->request->getParam("md")["permissions"] ?? '';
		if ($permissionJson != '') {
			$permissions = ScienceMeshSharePermissions::fromJson($permissionJson);
		} else {
			$permissions = new ScienceMeshSharePermissions();
		}
		$permissionCode = $permissions->getCode();
		$grantee = null;
		$owner = null;
		$creator = null;
		$granteeArray = $this->request->getParam("g")["grantee"]["Id"]["UserId"] ?? '';
		if ($granteeArray != '') {
			$grantee = ScienceMeshUserId::fromArray($granteeArray);
		}
		$ownerArray = $this->request->getParam("md")["owner"] ?? '';
		if ($ownerArray != '') {
			$owner = ScienceMeshUserId::fromArray($ownerArray);
		}
		$creatorArray = $this->request->getParam("md")["creator"] ?? '';
		if ($creatorArray != '') {
			$creator = ScienceMeshUserId::fromArray($creatorArray);
		}
		$mtime = $this->request->getParam("md")["mtime"] ?? 0;
		$ctime = $this->request->getParam("md")["ctime"] ?? 0;
		$sharedSecret = $this->request->getParam("protocol")["options"]["sharedSecret"] || '';
		$name = $this->getNameByOpaqueId($opaqueId);
		$sharedBy = $this->getSharedByOpaqueId($opaqueId);
		if (
			!isset($providerDomain) ||
			!isset($providerId) ||
			!isset($opaqueId) ||
			!isset($name) ||
			!isset($sharedBy) ||
			!isset($userId)
		) {
			return new JSONResponse(
				['message' => 'Missing arguments: $providerDomain: ' . $providerDomain . ' $providerId: ' . $providerId . ' $opaqueId: ' . $opaqueId . ' $name: ' . $name . ' $sharedBy: ' . $sharedBy . ' $userId: ' . $userId],
				Http::STATUS_BAD_REQUEST
			);
		}
		try {
			if ($this->shareProvider->getReceivedShareByToken($opaqueId)) {
				return new JSONResponse(["Already received this share"], Http::STATUS_ACCEPTED);
			}
		} catch (ShareNotFound $e) {
		}
		$scienceMeshData = [
			'opaque_id' => $opaqueId,
			'resource_id' => $resourceId,
			'permissions' => $permissionCode,
			'is_external' => true,
			'mtime' => $mtime,
			'ctime' => $ctime
		];

		$grantee && $scienceMeshData['grantee'] = $grantee;
		$owner && $scienceMeshData['owner'] = $owner;
		$creator && $scienceMeshData['creator'] = $creator;
		$shareData = [
			$providerDomain,
			$providerId,
			$opaqueId,
			$sharedSecret,
			$name,
			$sharedBy,
			$userId
		];
		$id = $this->shareProvider->addScienceMeshShare($scienceMeshData,$shareData);
		$response = [
			"id" => $id,
			"resource_id" => $opaqueId,
			"permissions" => $permissions->getArray(),
			"grantee" => [
				"Id" => [
					"UserId" => $grantee->asArray()
				]
			],
			"owner" => $owner?$owner->asArray():[],
			"creator" => $creator?$creator->asArray():[],
			"ctime" => [
				"seconds" => 1234567890
			],
			"mtime" => [
				"seconds" => 1234567890
			]
		];
		return new JSONResponse($response, 201);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 *
	 * Remove Share from share table
	 */
	public function Unshare($userId) {
		$this->init($userId);
		$opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
		$name = $this->getNameByOpaqueId($opaqueId);
		if ($this->shareProvider->deleteSentShareByName($userId, $name)) {
			return new JSONResponse("Deleted Sent Share",Http::STATUS_OK);
		} else {
			if ($this->shareProvider->deleteReceivedShareByOpaqueId($userId, $opaqueId)) {
				return new JSONResponse("Deleted Received Share",Http::STATUS_OK);
			} else {
				return new JSONResponse("Could not find share", Http::STATUS_BAD_REQUEST);
			}
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 */
	public function UpdateSentShare($userId) {
		$this->init($userId);
		$opaqueId = $this->request->getParam("ref")["Spec"]["Id"]["opaque_id"];
		$permissions = $this->request->getParam("p")["permissions"];
		$permissionsCode = $this->getPermissionsCode($permissions);
		$name = $this->getNameByOpaqueId($opaqueId);
		if (!($share = $this->shareProvider->getSentShareByName($userId,$name))) {
			return new JSONResponse(["error" => "UpdateSentShare failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$share->setPermissions($permissionsCode);
		$shareUpdated = $this->shareProvider->update($share);
		$response = $this->shareInfoToResourceInfo($shareUpdated);
		return new JSONResponse($response, Http::STATUS_OK);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 * UpdateReceivedShare updates the received share with share state.
	 */
	public function UpdateReceivedShare($userId) {
		$this->init($userId);
		$response = [];
		$resourceId = $this->request->getParam("received_share")["share"]["resource_id"];
		$permissions = $this->request->getParam("received_share")["share"]["permissions"];
		$permissionsCode = $this->getPermissionsCode($permissions);
		try {
			$share = $this->shareProvider->getReceivedShareByToken($resourceId);
			$share->setPermissions($permissionsCode);
			$shareUpdate = $this->shareProvider->UpdateReceivedShare($share);
			$response = $this->shareInfoToResourceInfo($shareUpdate);
			$response["state"] = 2;
			return new JSONResponse($response, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new JSONResponse(["error" => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 * ListSentShares returns the shares created by the user. If md is provided is not nil,
	 * it returns only shares attached to the given resource.
	 */
	public function ListSentShares($userId) {
		$this->init($userId);
		$responses = [];
		$shares = $this->shareProvider->getSentShares($userId);
		if ($shares) {
			foreach ($shares as $share) {
				array_push($responses,$this->shareInfoToResourceInfo($share));
			}
		}
		return new JSONResponse($responses, Http::STATUS_OK);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 * ListReceivedShares returns the list of shares the user has access.
	 */
	public function ListReceivedShares($userId) {
		$this->init($userId);
		$responses = [];
		$shares = $this->shareProvider->getReceivedShares($userId);
		if ($shares) {
			foreach ($shares as $share) {
				$response = $this->shareInfoToResourceInfo($share);
				$response["state"] = 2;
				array_push($responses, $response);
			}
		}
		return new JSONResponse($responses, Http::STATUS_OK);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 * GetReceivedShare returns the information for a received share the user has access.
	 */
	public function GetReceivedShare($userId) {
		$this->init($userId);
		$opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
		$name = $this->getNameByOpaqueId($opaqueId);
		try {
			$share = $this->shareProvider->getReceivedShareByToken($opaqueId);
			$response = $this->shareInfoToResourceInfo($share);
			$response["state"] = 2;
			return new JSONResponse($response, Http::STATUS_OK);
		} catch (\Exception $e) {
			return new JSONResponse(["error" => $e->getMessage()],Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 * GetSentShare gets the information for a share by the given ref.
	 */
	public function GetSentShare($userId) {
		$this->init($userId);
		$opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
		$name = $this->getNameByOpaqueId($opaqueId);
		$share = $this->shareProvider->getSentShareByName($userId,$name);
		if ($share) {
			$response = $this->shareInfoToResourceInfo($share);
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "GetSentShare failed"], Http::STATUS_BAD_REQUEST);
	}
}
