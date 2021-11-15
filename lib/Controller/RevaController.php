<?php

namespace OCA\ScienceMesh\Controller;

use OCA\ScienceMesh\PlainResponse;
use OCA\ScienceMesh\NextcloudAdapter;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;

use OCA\Files_Trashbin\Trash\ITrashManager;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\IConfig;

use OCP\Files\IRootFolder;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\QueryException;

use OCA\CloudFederationAPI\Config;
use OCP\Federation\Exceptions\ProviderCouldNotAddShareException;
use OCP\Federation\Exceptions\ProviderDoesNotExistsException;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;

use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Share\Exceptions\ShareNotFound;

use OCP\Constants;

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

		$this->config = new \OCA\ScienceMesh\ServerConfig($config, $urlGenerator, $userManager);

		$this->trashManager = $trashManager;
		$this->shareManager = $shareManager;
		$this->groupManager = $groupManager;
		$this->cloudFederationProviderManager = $cloudFederationProviderManager;
		$this->factory = $factory;
		$this->cloudIdManager = $cloudIdManager;
		$this->logger = $logger;
		$this->appManager = $appManager;
		$this->l = $l10n;

		$this->userFolder = $this->rootFolder->getUserFolder($userId);
		// Create the Nextcloud Adapter
		$adapter = new NextcloudAdapter($this->userFolder);
		$this->filesystem = new \League\Flysystem\Filesystem($adapter);
		$this->baseUrl = $this->getStorageUrl($userId); // Where is that used?
		$this->shareProvider = $shareProvider;
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
	 * @param string $path
	 * @return \OCP\Share\IShare
	 * @throws ShareNotFound
	 */
	private function getShareByPath($path) {
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	private function getShareType($granteeType) {
		if ($granteeType == 1) {
			return 'user';
		} elseif ($granteeType == 2) {
			return 'group';
		}
		return new JSONResponse(
			['message' => 'Internal error at ' . $this->urlGenerator->getBaseUrl()],
			Http::STATUS_BAD_REQUEST
		);
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

	private function respond($responseBody, $statusCode, $headers = []) {
		$result = new PlainResponse($body);
		foreach ($headers as $header => $values) {
			foreach ($values as $value) {
				$result->addHeader($header, $value);
			}
		}
		$result->setStatus($statusCode);
		return $result;
	}

	/* Reva handlers */

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function AddGrant($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a param with a grant to add here;
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function Authenticate($userId) {
		$password = $this->request->getParam("password");
		// Try e.g.:
		// curl -v -H 'Content-Type:application/json' -d'{"password":"relativity"}' http://localhost/apps/sciencemesh/~einstein/api/Authenticate
		// FIXME: https://github.com/pondersource/nc-sciencemesh/issues/3
		$auth = $this->userManager->checkPassword($userId,$password);
		if ($auth) {
			return new JSONResponse("Logged in", Http::STATUS_OK);
		}
		return new JSONResponse("Username / password not recognized", 401);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function CreateDir($userId) {
		$path = "sciencemesh" . $this->request->getParam("path"); // FIXME: sanitize the input
		$success = $this->filesystem->createDir($path);
		if ($success) {
			return new JSONResponse("OK", Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "Could not create directory."], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function CreateHome($userId) {
		$homeExists = $this->userFolder->nodeExists("sciencemesh");
		if (!$homeExists) {
			$this->userFolder->newFolder("sciencemesh"); // Create the Sciencemesh directory for storage if it doesn't exist.
		}
		return new JSONResponse("OK", Http::STATUS_OK);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function CreateReference($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: normalize incoming path
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function Delete($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: normalize incoming path
		$success = $this->filesystem->delete($path);
		if ($success) {
			return new JSONResponse("OK", Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "Failed to delete."], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function EmptyRecycle($userId) {
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
	 */
	public function GetMD($userId) {
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
	 */
	public function GetPathByID($userId) {
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
	 */
	public function InitiateUpload($userId) {
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
	 */

	public function ListFolder($userId) {
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
	 */
	public function ListGrants($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented",Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function ListRecycle($userId) {
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
	 */
	public function ListRevisions($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented",Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function Move($userId) {
		$from = $this->request->getParam("from");
		$to = $this->request->getParam("to");
		$success = $this->filesystem->move($from, $to);
		if ($success) {
			return new JSONResponse("OK", Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "Failed to move."], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function RemoveGrant($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a grant to remove here;
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function RestoreRecycleItem($userId) {
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
	 */
	public function RestoreRevision($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a revision param here;
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function SetArbitraryMetadata($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		$metadata = $this->request->getParam("metadata");
		// FIXME: What do we do with the existing metadata? Just toss it and overwrite with the new value? Or do we merge?
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function UnsetArbitraryMetadata($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function UpdateGrant($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a paramater with the grant(s)
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function Upload($userId, $path) {
		$contents = $this->request->put;
		if ($this->filesystem->has("/sciencemesh" . $path)) {
			$success = $this->filesystem->update("/sciencemesh" . $path, $contents);
			if ($success) {
				return new JSONResponse("OK", Http::STATUS_OK);
			} else {
				return new JSONResponse(["error" => "Update failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}
		$success = $this->filesystem->write("/sciencemesh" . $path, $contents);
		if ($success) {
			return new JSONResponse("OK", Http::STATUS_CREATED);
		}
		return new JSONResponse(["error" => "Create failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 * @suppress PhanUndeclaredClassMethod
	 *
	 * @return DataResponse
	 * @throws NotFoundException
	 * @throws OCSBadRequestException
	 * @throws OCSException
	 * @throws OCSForbiddenException
	 * @throws OCSNotFoundException
	 * @throws InvalidPathException
	 * Create a new share in fn with the given access control list.
	 */
	public function addSentShare($userId) {
		$publicUpload = 'false';
		$password = '';
		$sendPasswordByTalk = null;
		$expireDate = '';
		$label = '';
		$shareType = IShare::TYPE_LINK;

		$md = $this->request->getParam("md");
		$g = $this->request->getParam("g");
		$opaqueId = $md["opaque_id"];

		$opaqueIdDecoded = urldecode($opaqueId);
		$opaqueIdExploded = explode("/",$opaqueIdDecoded);
		//$name resource name (e.g. document.odt)
		$name = end($opaqueIdExploded);
		$grantee = $g["grantee"];
		$granteeId = $grantee["Id"];
		$granteeIdUserId = $granteeId["UserId"];
		$shareWith = $granteeIdUserId["opaque_id"]."@".$granteeIdUserId["idp"];
		$sharePermissions = $g["permissions"];

		$resourcePermissions = $sharePermissions["permissions"];
		$permissions = $this->getPermissionsCode($resourcePermissions);
		$share = $this->shareManager->newShare();

		if ($permissions === null) {
			$permissions = $this->config->getAppValue('core', 'shareapi_default_permissions', Constants::PERMISSION_ALL);
		}

		// Verify path
		if ($name === null) {
			throw new OCSNotFoundException($this->l->t('Please specify a file or folder path'));
		}

		try {
			$path = $this->userFolder->get("sciencemesh/".$name);
		} catch (NotFoundException $e) {
			return new JSONResponse(["error" => "Share failed. Resource Path not found"], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$share->setNode($path);

		try {
			$this->lock($share->getNode());
		} catch (LockedException $e) {
			throw new OCSNotFoundException($this->l->t('Could not create share'));
		}

		if ($permissions < 0 || $permissions > Constants::PERMISSION_ALL) {
			throw new OCSNotFoundException($this->l->t('Invalid permissions'));
		}

		// Shares always require read permissions
		$permissions |= Constants::PERMISSION_READ;

		if ($path instanceof \OCP\Files\File) {
			// Single file shares should never have delete or create permissions
			$permissions &= ~Constants::PERMISSION_DELETE;
			$permissions &= ~Constants::PERMISSION_CREATE;
		}
		if ($path->getStorage()->instanceOfStorage(Storage::class)) {
			$permissions &= ~($permissions & ~$path->getPermissions());
		}

		if ($shareType === IShare::TYPE_USER) {
			// Valid user is required to share
			if ($shareWith === null || !$this->userManager->userExists($shareWith)) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid user'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_GROUP) {
			if (!$this->shareManager->allowGroupSharing()) {
				throw new OCSNotFoundException($this->l->t('Group sharing is disabled by the administrator'));
			}

			// Valid group is required to share
			if ($shareWith === null || !$this->groupManager->groupExists($shareWith)) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid group'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_LINK
			|| $shareType === IShare::TYPE_EMAIL) {

			// Can we even share links?
			if (!$this->shareManager->shareApiAllowLinks()) {
				throw new OCSNotFoundException($this->l->t('Public link sharing is disabled by the administrator'));
			}

			if ($publicUpload === 'true') {
				// Check if public upload is allowed
				if (!$this->shareManager->shareApiLinkAllowPublicUpload()) {
					throw new OCSForbiddenException($this->l->t('Public upload disabled by the administrator'));
				}

				// Public upload can only be set for folders
				if ($path instanceof \OCP\Files\File) {
					throw new OCSNotFoundException($this->l->t('Public upload is only possible for publicly shared folders'));
				}

				$permissions = Constants::PERMISSION_READ |
					Constants::PERMISSION_CREATE |
					Constants::PERMISSION_UPDATE |
					Constants::PERMISSION_DELETE;
			} else {
				$permissions = Constants::PERMISSION_READ;
			}

			// TODO: It might make sense to have a dedicated setting to allow/deny converting link shares into federated ones
			if (($permissions & Constants::PERMISSION_READ) && $this->shareManager->outgoingServer2ServerSharesAllowed()) {
				$permissions |= Constants::PERMISSION_SHARE;
			}

			$share->setPermissions($permissions);

			// Set password
			if ($password !== '') {
				$share->setPassword($password);
			}

			// Only share by mail have a recipient
			if (is_string($shareWith) && $shareType === IShare::TYPE_EMAIL) {
				$share->setSharedWith($shareWith);
			}

			// If we have a label, use it
			if (!empty($label)) {
				$share->setLabel($label);
			}

			if ($sendPasswordByTalk === 'true') {
				if (!$this->appManager->isEnabledForUser('spreed')) {
					throw new OCSForbiddenException($this->l->t('Sharing %s sending the password by Nextcloud Talk failed because Nextcloud Talk is not enabled', [$path->getPath()]));
				}

				$share->setSendPasswordByTalk(true);
			}

			//Expire date
			if ($expireDate !== '') {
				try {
					$expireDate = $this->parseDate($expireDate);
					$share->setExpirationDate($expireDate);
				} catch (\Exception $e) {
					throw new OCSNotFoundException($this->l->t('Invalid date, date format must be YYYY-MM-DD'));
				}
			}
		} elseif ($shareType === IShare::TYPE_REMOTE) {
			if (!$this->shareManager->outgoingServer2ServerSharesAllowed()) {
				throw new OCSForbiddenException($this->l->t('Sharing %1$s failed because the back end does not allow shares from type %2$s', [$path->getPath(), $shareType]));
			}

			if ($shareWith === null) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid federated user ID'));
			}

			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
			if ($expireDate !== '') {
				try {
					$expireDate = $this->parseDate($expireDate);
					$share->setExpirationDate($expireDate);
				} catch (\Exception $e) {
					throw new OCSNotFoundException($this->l->t('Invalid date, date format must be YYYY-MM-DD'));
				}
			}
		} elseif ($shareType === IShare::TYPE_REMOTE_GROUP) {
			if (!$this->shareManager->outgoingServer2ServerGroupSharesAllowed()) {
				throw new OCSForbiddenException($this->l->t('Sharing %1$s failed because the back end does not allow shares from type %2$s', [$path->getPath(), $shareType]));
			}

			if ($shareWith === null) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid federated group ID'));
			}

			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
			if ($expireDate !== '') {
				try {
					$expireDate = $this->parseDate($expireDate);
					$share->setExpirationDate($expireDate);
				} catch (\Exception $e) {
					throw new OCSNotFoundException($this->l->t('Invalid date, date format must be YYYY-MM-DD'));
				}
			}
		} elseif ($shareType === IShare::TYPE_CIRCLE) {
			if (!\OC::$server->getAppManager()->isEnabledForUser('circles') || !class_exists('\OCA\Circles\ShareByCircleProvider')) {
				throw new OCSNotFoundException($this->l->t('You cannot share to a Circle if the app is not enabled'));
			}

			$circle = \OCA\Circles\Api\v1\Circles::detailsCircle($shareWith);

			// Valid circle is required to share
			if ($circle === null) {
				throw new OCSNotFoundException($this->l->t('Please specify a valid circle'));
			}
			$share->setSharedWith($shareWith);
			$share->setPermissions($permissions);
		} elseif ($shareType === IShare::TYPE_ROOM) {
			try {
				$this->getRoomShareHelper()->createShare($share, $shareWith, $permissions, $expireDate);
			} catch (QueryException $e) {
				throw new OCSForbiddenException($this->l->t('Sharing %s failed because the back end does not support room shares', [$path->getPath()]));
			}
		} elseif ($shareType === IShare::TYPE_DECK) {
			try {
				$this->getDeckShareHelper()->createShare($share, $shareWith, $permissions, $expireDate);
			} catch (QueryException $e) {
				throw new OCSForbiddenException($this->l->t('Sharing %s failed because the back end does not support room shares', [$path->getPath()]));
			}
		} else {
			throw new OCSBadRequestException($this->l->t('Unknown share type'));
		}
		$share->setShareType($shareType);
		$share->setSharedBy($userId);

		try {
			$share = $this->shareManager->createShare($share);
		} catch (GenericShareException $e) {
			\OC::$server->getLogger()->logException($e);
			$code = $e->getCode() === 0 ? 403 : $e->getCode();
			throw new OCSException($e->getHint(), $code);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->logException($e);
			throw new OCSForbiddenException($e->getMessage(), $e);
		}
		$response = $this->shareInfoToResourceInfo($share);
		return new JSONResponse($response, Http::STATUS_CREATED);
	}

	/**
	 * add a received share
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 * @BruteForceProtection(action=receiveFederatedShare)
	 *
	 * @return Http\DataResponse|JSONResponse
	 */

	public function addReceivedShare($userId) {
		$md = $this->request->getParam("md");
		$g = $this->request->getParam("g");
		// $providerId resource UID on the provider side
		$providerId = $this->request->getParam("provider_id");
		// $resourceType ('file', 'calendar',...)
		$resourceType = $this->request->getParam("resource_type");
		$providerDomain = $this->request->getParam("provider_domain");
		// $ownerDisplayName display name of the user who shared the item
		$ownerDisplayName = $this->request->getParam("owner_display_name");
		// $protocol (e,.g. ['name' => 'webdav', 'options' => ['username' => 'john', 'permissions' => 31]])
		$protocol = $this->request->getParam("protocol");
		$opaqueId = $md["opaque_id"];
		$opaqueIdDecoded = urldecode($opaqueId);
		$opaqueIdExploded = explode("/",$opaqueIdDecoded);
		//$name resource name (e.g. document.odt)
		$name = end($opaqueIdExploded);
		// $sharedByDisplayName display name of the user who shared the resource
		$sharedByDisplayName = '';
		$ownerName = substr($opaqueIdExploded[0],strlen("fileid-"));
		$description = '';
		$grantee = $g["grantee"];
		$granteeId = $grantee["Id"];
		$granteeIdUserId = $granteeId["UserId"];
		$shareWith = $userId."@".$granteeIdUserId["idp"];
		// $owner provider specific UID of the user who owns the resource
		$owner = $ownerName."@".$providerDomain;

		//$shareType ('group' or 'user' share)
		$shareType = $this->getShareType($grantee["type"]);

		// $sharedBy provider specific UID of the user who shared the resource
		$sharedBy = $owner;
	
		// check if all required parameters are set
		if ($shareWith === null ||
			$name === null ||
			$providerId === null ||
			$owner === null ||
			$resourceType === null ||
			$shareType === null ||
			!isset($protocol['name'])
		) {
			return new JSONResponse(
				['message' => 'Missing arguments'],
				Http::STATUS_BAD_REQUEST
			);
		}

		$cloudId = $this->cloudIdManager->resolveCloudId($shareWith);
		$shareWith = $cloudId->getUser();

		if ($shareType === 'user') {
			$shareWith = $this->mapUid($shareWith);

			if (!$this->userManager->userExists($shareWith)) {
				return new JSONResponse(
					['message' => 'User "' . $shareWith . '" does not exists at ' . $this->urlGenerator->getBaseUrl()],
					Http::STATUS_BAD_REQUEST
				);
			}
		}

		if ($shareType === 'group') {
			if (!$this->groupManager->groupExists($shareWith)) {
				return new JSONResponse(
					['message' => 'Group "' . $shareWith . '" does not exists at ' . $this->urlGenerator->getBaseUrl()],
					Http::STATUS_BAD_REQUEST
				);
			}
		}
		// if no explicit display name is given, we use the uid as display name
		$ownerDisplayName = $ownerDisplayName === null ? $owner : $ownerDisplayName;
		$sharedByDisplayName = $sharedByDisplayName === null ? $sharedBy : $sharedByDisplayName;

		// sharedBy* parameter is optional, if nothing is set we assume that it is the same user as the owner
		if ($sharedBy === null) {
			$sharedBy = $owner;
			$sharedByDisplayName = $ownerDisplayName;
		}

		try {
			$provider = $this->cloudFederationProviderManager->getCloudFederationProvider($resourceType);
			$share = $this->factory->getCloudFederationShare($shareWith, $name, $description, $providerId, $owner, $ownerDisplayName, $sharedBy, $sharedByDisplayName, '', $shareType, $resourceType);
			$share->setProtocol($protocol);
			$provider->shareReceived($share);
		} catch (ProviderDoesNotExistsException $e) {
			return new JSONResponse(
				['message' => $e->getMessage()],
				Http::STATUS_NOT_IMPLEMENTED
			);
		} catch (ProviderCouldNotAddShareException $e) {
			return new JSONResponse(
				['message' => $e->getMessage()],
				$e->getCode()
			);
		} catch (\Exception $e) {
			return new JSONResponse(
				['message' => 'Internal error at ' . $this->urlGenerator->getBaseUrl()],
				Http::STATUS_BAD_REQUEST
			);
		}

		$user = $this->userManager->get($shareWith);
		$recipientDisplayName = '';
		if ($user) {
			$recipientDisplayName = $user->getDisplayName();
		}
		$response = '{"id":{},"resource_id":{},"permissions":{"permissions":{"add_grant":true,"create_container":true,"delete":true,"get_path":true,"get_quota":true,"initiate_file_download":true,"initiate_file_upload":true,"list_grants":true,"list_container":true,"list_file_versions":true,"list_recycle":true,"move":true,"remove_grant":true,"purge_recycle":true,"restore_file_version":true,"restore_recycle_item":true,"stat":true,"update_grant":true,"deny_grant":true}},"grantee":{"Id":{"UserId":{"idp":"0.0.0.0:19000","opaque_id":"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c","type":1}}},"owner":{"idp":"0.0.0.0:19000","opaque_id":"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c","type":1},"creator":{"idp":"0.0.0.0:19000","opaque_id":"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c","type":1},"ctime":{"seconds":1234567890},"mtime":{"seconds":1234567890}}';
		return new JSONResponse(json_decode($response), 201);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * GetShare gets the information for a share by the given ref.
	 */
	public function GetShare($userId) {
		$spec = $this->request->getParam("Spec");
		$Id = $spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$share = $this->shareManager->getShareById($opaqueId);
		if ($share) {
			$response = $this->shareInfoToResourceInfo($share);
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "GetShare failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * Unshare deletes the share pointed by ref.
	 */
	public function Unshare($userId) {
		$spec = $this->request->getParam("Spec");
		$id = $spec["Id"];
		$opaqueId = $id["opaque_id"];
		error_log("opaque_id: ".$opaqueId);
		try {
			$share = $this->getShareById($opaqueId,$userId);
		} catch (ShareNotFound $e) {
			error_log("getShareById fails");
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		try {
			$this->lock($share->getNode());
		} catch (LockedException $e) {
			throw new OCSNotFoundException($this->l->t('Could not delete share'));
		}

		if (!$this->canAccessShare($share,$userId)) {
			throw new OCSNotFoundException($this->l->t('Wrong share ID, share doesn\'t exist'));
		}

		if (!$this->canDeleteShare($share)) {
			throw new OCSForbiddenException($this->l->t('Could not delete share'));
		}
		$success = $this->shareManager->deleteShare($share);
		if ($success) {
			return new JSONResponse("OK", Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "Unshare failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 */

	public function UpdateShare($userId) {
		$ref = $this->request->getParam("ref");
		$spec = $ref["Spec"];
		$id = $spec["Id"];
		$opaqueId = $id["opaque_id"];
		$p = $this->request->getParam("p");
		$permissions = $p["permissions"];
		$permissionsCode = $this->getPermissionsCode($permissions);
		$share = $this->shareManager->getShareById($opaqueId);
		$updated = $this->shareManager->updateShare($share, $permissionsCode);
		if ($updated) {
			$response = $this->shareInfoToResourceInfo($updated);
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "UpdateShare failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * ListShares returns the shares created by the user. If md is provided is not nil,
	 * it returns only shares attached to the given resource.
	 */
	public function ListShares($userId) {
		$requests = $this->request->getParams();
		$request = array_values($requests)[2];
		$type = $request["type"];
		$term = $request["Term"];
		$creator = $term["Creator"];
		$idpCreator = $creator["idp"];
		$opaqueIdCreator = ["opaque_id"];
		$typeCreator = ["type"];
		$responses = [];
		$shares = $this->shareManager->getSharesBy($userId, 6);
		if ($shares) {
			foreach ($shares as $share) {
				array_push($responses,$this->shareInfoToResourceInfo($share));
			}
			return new JSONResponse($responses, Http::STATUS_OK);
		} elseif ($shares == []) {
			return new JSONResponse($shares, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "ListShares failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * ListReceivedShares returns the list of shares the user has access.
	 */
	public function ListReceivedShares($userId) {
		$responses = [];
		$shares = $this->shareProvider->getExternalShares();
		if ($shares) {
			foreach ($shares as $share) {
				$response = $this->shareInfoToResourceInfo($share);
				$response["state"] = 2;
				array_push($responses, $response);
			}
			return new JSONResponse($responses, Http::STATUS_OK);
		} else {
			return new JSONResponse($shares, Http::STATUS_OK);
		}
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * GetReceivedShare returns the information for a received share the user has access.
	 */
	public function GetReceivedShare($userId) {
		$spec = $this->request->getParam("Spec");
		$Id = $spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$share = $this->shareManager->getShareById($opaqueId,$userId);
		if ($share) {
			$response = $this->shareInfoToResourceInfo($share);
			$response["state"] = 2;
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "GetReceivedShare failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * UpdateReceivedShare updates the received share with share state.
	 */
	public function UpdateReceivedShare($userId) {
		$ref = $this->request->getParam("ref");
		$Spec = $ref["Spec"];
		$Id = $Spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$share = $this->shareManager->getShareById($opaqueId,$userId);
		$updated = $this->shareManager->updateShare($share, 5);
		if ($updated) {
			$response = $this->shareInfoToResourceInfo($updated);
			$response["state"] = 2;
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "UpdateReceivedShare failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * map login name to internal LDAP UID if a LDAP backend is in use
	 *
	 * @param string $uid
	 * @return string mixed
	 */
	private function mapUid($uid) {
		// FIXME this should be a method in the user management instead
		$this->logger->debug('shareWith before, ' . $uid, ['app' => $this->appName]);
		\OCP\Util::emitHook(
			'\OCA\Files_Sharing\API\Server2Server',
			'preLoginNameUsedAsUserName',
			['uid' => &$uid]
		);
		$this->logger->debug('shareWith after, ' . $uid, ['app' => $this->appName]);

		return $uid;
	}


	/**
	 * Since we have multiple providers but the OCS Share API v1 does
	 * not support this we need to check all backends.
	 *
	 * @param string $id
	 * @return \OCP\Share\IShare
	 * @throws ShareNotFound
	 */
	private function getShareById(string $id,string $userId): IShare {
		$share = null;

		// First check if it is an internal share.
		try {
			$share = $this->shareManager->getShareById('ocinternal:' . $id,$userId);
			return $share;
		} catch (ShareNotFound $e) {
			// Do nothing, just try the other share type
		}
		try {
			$share = $this->shareManager->getShareById('ocRoomShare:' . $id,$userId);
			return $share;
		} catch (ShareNotFound $e) {
			// Do nothing, just try the other share type
		}
		if (!$this->shareManager->outgoingServer2ServerSharesAllowed()) {
			throw new ShareNotFound();
		}
		$share = $this->shareManager->getShareById('ocFederatedSharing:' . $id,$userId);

		return $share;
	}
	/**
	 * Does the user have read permission on the share
	 *
	 * @param \OCP\Share\IShare $share the share to check
	 * @param string userid
	 * @param boolean $checkGroups check groups as well?
	 * @return boolean
	 * @throws NotFoundException
	 *
	 * @suppress PhanUndeclaredClassMethod
	 */
	protected function canAccessShare(\OCP\Share\IShare $share,string $userId, bool $checkGroups = true): bool {
		// A file with permissions 0 can't be accessed by us. So Don't show it
		if ($share->getPermissions() === 0) {
			return false;
		}

		// Owner of the file and the sharer of the file can always get share
		if ($share->getShareOwner() === $userId
			|| $share->getSharedBy() === $userId) {
			return true;
		}
		// Have reshare rights on the shared file/folder ?
		// Does the currentUser have access to the shared file?
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$files = $userFolder->getById($share->getNodeId());
		if (!empty($files) && $this->shareProviderResharingRights($userId, $share, $files[0])) {
			return true;
		}
		return false;
	}
}
