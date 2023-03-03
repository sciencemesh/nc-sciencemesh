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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
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

define('RESTRICT_TO_SCIENCEMESH_FOLDER', false);
define('NEXTCLOUD_PREFIX', (RESTRICT_TO_SCIENCEMESH_FOLDER ? 'sciencemesh/' : ''));
define('REVA_PREFIX', '/home/'); // See https://github.com/pondersource/sciencemesh-php/issues/96#issuecomment-1298656896

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
		error_log("RevaController init");
		$this->userId = $userId;
		$this->checkRevadAuth();
		if ($userId) {
			$this->userFolder = $this->rootFolder->getUserFolder($userId);
		}
	}

	private function revaPathToNextcloudPath($revaPath) {
		$ret = NEXTCLOUD_PREFIX . substr($revaPath, strlen(REVA_PREFIX));
		error_log("Interpreting $revaPath as $ret");
    // return $this->userFolder->getPath() . NEXTCLOUD_PREFIX . substr($revaPath, strlen(REVA_PREFIX));
    return $ret;
	}

	private function nextcloudPathToRevaPath($nextcloudPath) {
    // return REVA_PREFIX . substr($nextcloudPath, strlen($this->userFolder->getPath() . NEXTCLOUD_PREFIX));
    return REVA_PREFIX . substr($nextcloudPath, strlen(NEXTCLOUD_PREFIX));
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
		error_log("checkRevadAuth");
		$authHeader = $this->request->getHeader('X-Reva-Secret');
    if ($authHeader != $this->config->getRevaSharedSecret()) {
		  throw new \OCP\Files\NotPermittedException('Please set an http request header "X-Reva-Secret: <your_shared_secret>"!');
		}
	}
	private function getRevaPathFromOpaqueId($opaqueId) {
		return substr($opaqueId, strlen("fileid-"));
	}

	private function nodeToCS3ResourceInfo(\OCP\Files\Node $node) : array {
		$isDirectory = ($node->getType() === \OCP\Files\FileInfo::TYPE_FOLDER);
		$nextcloudPath = substr($node->getPath(), strlen($this->userFolder->getPath()) + 1);
		$revaPath = $this->nextcloudPathToRevaPath($nextcloudPath);
		return [
			"opaque" => [
				"map" => null,
			],
			"type" => ($isDirectory ? 2 : 1),
			"id" => [
				"opaque_id" => "fileid-" . $revaPath,
			],
			"checksum" => [
				"type" => 0,
				"sum" => "",
			],
			"etag" => "deadbeef",
			"mime_type" => ($isDirectory ? "folder" : $node->getMimetype()),
			"mtime" => [
				"seconds" => $node->getMTime(),
			],
			"path" => $revaPath,
			"permission_set" => [
				"add_grant" => false,
				"create_container" => false,
				"delete" => false,
				"get_path" => false,
				"get_quota" => false,
				"initiate_file_download" => false,
				"initiate_file_upload" => false,
			],
			"size" => $node->getSize(),
			"canonical_metadata" => [
				"target" => null,
			],
			"arbitrary_metadata" => [
				"metadata" => [
					".placeholder" => "ignore",
				],
			],
			"owner" => [
				"opaque_id" => $this->userId,
				"idp" => $this->config->getIopUrl(),
			],
		];
	}

	# For ListReceivedShares, GetReceivedShare and UpdateReceivedShare we need to include "state:2"
	private function shareInfoToCs3Share(IShare $share): array {
		$shareeParts = explode("@", $share->getSharedWith());
		if (count($shareeParts) == 1) {
			$shareeParts = [ "unknown", "unknown" ];
		}
		$ownerParts = explode("@", $share->getShareOwner());
		if (count($ownerParts) == 1) {
			$ownerParts = [ "unknown", "unknown" ];
		}
		$stime = 0; // $share->getShareTime()->getTimeStamp();
		try {
		  $opaqueId = "fileid-" . $share->getNode()->getPath();
		} catch (\OCP\Files\NotFoundException $e) {
			$opaqueId = "unknown";
		}

		// produces JSON that maps to
		// https://github.com/cs3org/reva/blob/v1.18.0/pkg/ocm/share/manager/nextcloud/nextcloud.go#L77
		// and
		// https://github.com/cs3org/go-cs3apis/blob/d297419/cs3/sharing/ocm/v1beta1/resources.pb.go#L100
		return [
			"id" => [
				// https://github.com/cs3org/go-cs3apis/blob/d297419/cs3/sharing/ocm/v1beta1/resources.pb.go#L423
				"opaque_id" => $share->getId()
			],
			"resource_id" => [

			  "opaque_id"  => $opaqueId,
			],
			"permissions" => [
				"permissions" => [
					"add_grant" => false,
					"create_container" => false,
					"delete" => false,
					"get_path" => false,
					"get_quota" => false,
					"initiate_file_download" => false,
					"initiate_file_upload" => false,
				]
			],
			// https://github.com/cs3org/go-cs3apis/blob/d29741980082ecd0f70fe10bd2e98cf75764e858/cs3/storage/provider/v1beta1/resources.pb.go#L897
			"grantee" => [
				"type" => 1, // https://github.com/cs3org/go-cs3apis/blob/d29741980082ecd0f70fe10bd2e98cf75764e858/cs3/storage/provider/v1beta1/resources.pb.go#L135
			  "id" => [
					"opaque_id" => $shareeParts[0],
					"idp" => $shareeParts[1]
			  ],
			],
			"owner" => [
			  "id" => [
					"opaque_id" => $ownerParts[0],
					"idp" => $ownerParts[1]
			  ],
			],
			"creator" => [
				"id" => [
					"opaque_id" => $ownerParts[0],
					"idp" => $ownerParts[1]
			  ],
			],
			"ctime" => [
				"seconds" => $stime
			],
			"mtime" => [
				"seconds" => $stime
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
		error_log("AddGrant");
		$this->init($userId);
		
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
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
		error_log("Authenticate");
		$this->init($userId);
		$userId = $this->request->getParam("clientID");
		$password = $this->request->getParam("clientSecret");

		// Try e.g.:
		// curl -v -H 'Content-Type:application/json' -d'{"clientID":"einstein",clientSecret":"relativity"}' http://einstein:relativity@localhost/index.php/apps/sciencemesh/~einstein/api/auth/Authenticate

    // Ref https://github.com/cs3org/reva/issues/2356
		if ($password == $this->config->getRevaLoopbackSecret()) {
			$user = $this->userManager->get($userId);
		} else {
				$user = $this->userManager->checkPassword($userId, $password);
		}

		
		if ($user) {
			/// the `value` is a base64 encoded value of:
			/// {"resource_id":{"storage_id":"storage-id","opaque_id":"opaque-id"},"path":"some/file/path.txt"}
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
		error_log("CreateDir");
		$this->init($userId);
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
		try {
			$this->userFolder->newFolder($path);
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
		error_log("CreateHome");
		if (RESTRICT_TO_SCIENCEMESH_FOLDER) {
			$this->init($userId);
			$homeExists = $this->userFolder->nodeExists("sciencemesh");
			if (!$homeExists) {
				try {
					$this->userFolder->newFolder("sciencemesh"); // Create the Sciencemesh directory for storage if it doesn't exist.
				} catch (NotPermittedException $e) {
					return new JSONResponse(["error" => "Create home failed. Resource Path not foun"], Http::STATUS_INTERNAL_SERVER_ERROR);
				}
				return new JSONResponse("CREATED", Http::STATUS_CREATED);
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
	public function CreateReference($userId) {
		error_log("CreateReference");

		$this->init($userId);
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function CreateStorageSpace($userId) {
		error_log("CreateStorageSpace");
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
		error_log("Delete");

		$this->init($userId);
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
		try {
			$node = $this->userFolder->get($path);
			$node->delete($path);
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
		error_log("EmptyRecycle");
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
		error_log("GetMD");
		$this->init($userId);
		$ref = $this->request->getParam("ref");
		$path = $this->revaPathToNextcloudPath($ref["path"]); // FIXME: normalize incoming path
		error_log("Looking for nc path '$path' in user folder; reva path '".$ref["path"]."' ");
		$dirContents = $this->userFolder->getDirectoryListing();
		$paths = array_map(function (\OCP\Files\Node $node) {
			return $node->getPath();
		}, $dirContents);
		error_log("User folder ".$this->userFolder->getPath()." has: " . implode(",", $paths));
		$success = $this->userFolder->nodeExists($path);
		if ($success) {
			$node = $this->userFolder->get($path);
			$resourceInfo = $this->nodeToCS3ResourceInfo($node);
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
		return new DataResponse($path, Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function InitiateUpload($userId) {
		$ref = $this->request->getParam("ref");
		$path = $this->revaPathToNextcloudPath((isset($ref["path"]) ? $ref["path"] : ""));
		$this->init($userId);
		$response = [
			"simple" => $path
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
		$path = $this->revaPathToNextcloudPath((isset($ref["path"]) ? $ref["path"] : ""));
		$success = $this->userFolder->nodeExists($path);
		if (!$success) {
			return new JSONResponse(["error" => "Folder not found"], 404);
		}
		$node = $this->userFolder->get($path);
		$nodes = $node->getDirectoryListing();
		$resourceInfos = array_map(function (\OCP\Files\Node $node) {
			return $this->nodeToCS3ResourceInfo($node);
		}, $nodes);
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
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
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
				$path = $this->nextcloudPathToRevaPath($node->getOriginalLocation());
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
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
		return new JSONResponse("Not implemented",Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function RemoveGrant($userId) {
		$this->init($userId);
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
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

				if ($this->revaPathToNextcloudPath($key) == $node->getOriginalLocation()) {
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
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
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
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
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
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
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
		$path = $this->revaPathToNextcloudPath($this->request->getParam("path"));
		// FIXME: Expected a paramater with the grant(s)
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * Write a new file.
	 *
	 * @param string $path
	 * @param string $contents
	 * @param Config $config Config object
	 *
	 * @return bool false on failure, true on success
	 *
	 * @throws \OCP\Files\InvalidPathException
	 */
	private function write($path, $contents, Config $config) {
		try {
			if ($this->userFolder->nodeExists($path)) {
				$node = $this->userFolder->get($path);
				$node->putContent($contents);
			} else {
			}
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 */
	public function Upload($userId, $path) {
		$revaPath = "/$path";
		error_log("RevaController Upload! $userId $revaPath");
		try {
			$this->init($userId);
			$contents = $entityBody = file_get_contents('php://input');
			// error_log("PUT body = " . var_export($contents, true));
			error_log("Uploading! $revaPath");
			$ncPath = $this->revaPathToNextcloudPath($revaPath);
			if ($this->userFolder->nodeExists($ncPath)) {
				$node = $this->userFolder->get($ncPath);
				$node->putContent($contents);
				return new JSONResponse("OK", Http::STATUS_OK);
			} else {
				$filename = basename($ncPath);
				$dirname = dirname($ncPath);
				if (!$this->userFolder->nodeExists($dirname)) {
					$this->userFolder->newFolder($dirname);
				}
				$node = $this->userFolder->get($dirname);
				$node->newFile($filename, $contents);
				return new JSONResponse("CREATED", Http::STATUS_CREATED);	
			}
		} catch (\Exception $e) {
			return new JSONResponse(["error" => "Upload failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
	
}
