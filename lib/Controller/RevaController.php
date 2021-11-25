<?php

namespace OCA\ScienceMesh\Controller;

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
	 * @param string $opaqueId
	 * @return int $shareId
	 * @throws OCSNotFoundException
	 */
	// private function getShareIdByPath($path){
	// 	if($path) {
	// 		try {
	// 			$node = $this->userFolder->get('sciencemesh/'.$path);
	// 			$this->lock($node);
	// 		} catch (NotFoundException $e) {
	// 			throw new OCSNotFoundException(
	// 				$this->l->t('Wrong path, file/folder doesn\'t exist')
	// 			);
	// 		} catch (LockedException $e) {
	// 			throw new OCSNotFoundException($this->l->t('Could not lock node'));
	// 		}
	// 		$shareId = $node->getId();
	// 		error_log('id: '.$shareId);
	// 		return $shareId;
	// 	}
	// 	return false;
	// }
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
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a param with a grant to add here;
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
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
	 * @return Http\DataResponse|JSONResponse
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function CreateDir($userId) {
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
		$homeExists = $this->userFolder->nodeExists("sciencemesh");
		if (!$homeExists) {
			try {
				$this->userFolder->newFolder("sciencemesh"); // Create the Sciencemesh directory for storage if it doesn't exist.
			} catch (NotPermittedException $e) {
				return new JSONResponse(["error" => "Create home failed. Resource Path not foun"], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			return new JSONResponse("CREATED", Http::STATUS_CREATED);
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
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: normalize incoming path
		return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 * @throws FileNotFoundException
	 */
	public function Delete($userId) {
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
		$userToCheck = $this->request->getParam('opaque_id');
		$response = [
			"id" => [
				"idp" => "some-domain.com",
				"opaque_id" => $userToCheck,
				"type" => 1
			]
		];
		if ($this->userManager->userExists($userToCheck)) {
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
		$md = $this->request->getParam("md");
		$g = $this->request->getParam("g");

		$opaqueId = $md["opaque_id"];
		$opaqueIdDecoded = urldecode($opaqueId);
		$opaqueIdExploded = explode("/",$opaqueIdDecoded);
		//$name resource name (e.g. document.odt)
		$name = end($opaqueIdExploded);
		if ($name === "") {
			throw new OCSNotFoundException($this->l->t('Please specify a file or folder path'));
		}
		$grantee = $g["grantee"];
		$granteeId = $grantee["Id"];
		$granteeIdUserId = $granteeId["UserId"];
		$shareWith = $granteeIdUserId["opaque_id"]."@".$granteeIdUserId["idp"];

		$sharePermissions = $g["permissions"];
		$resourcePermissions = $sharePermissions["permissions"];
		$permissions = $this->getPermissionsCode($resourcePermissions);
		$share = $this->shareManager->newShare();
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
		$share->setShareType(14);//IShare::TYPE_SCIENCEMESH);
		$share->setSharedBy($userId);
		$share->setSharedWith($shareWith);
		$share->setShareOwner($userId);
		$share->setPermissions($permissions);
		$this->shareProvider->create($share);
		// @TODO We need to use ScienceMeshShareProvider to store the share addSentShareToDB()
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
		$providerDomain = $this->request->getParam("provider_domain");
		$providerId = $this->request->getParam("provider_id");
		$opaqueId = urldecode($this->request->getParam("md")["opaque_id"]);
		$sharedSecret = $this->request->getParam("protocol")["options"]["sharedSecret"] || '';
		$exploded = explode("/", $opaqueId);
		$name = end($exploded);
		$sharedBy = substr($exploded[0], strlen("fileid-"));
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
		$id = $this->shareProvider
			->addReceivedShareToDB(
				$providerDomain,
				$providerId,
				$opaqueId,
				$sharedSecret,
				$name,
				$sharedBy,
				$userId);
		$response = [
			"id" => $id,
			"resource_id" => $opaqueId,
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
			"grantee" => $this->request->getParam("g")["grantee"],
			"owner" => $this->request->getParam("g")["grantee"]["Id"]["UserId"],
			"creator" => $this->request->getParam("g")["grantee"]["Id"]["UserId"],
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
		$opaque_id = $this->request->getParam("Spec")["Id"]["opaque_id"];
		if ($this->shareProvider->unshareByOpaqueId($userId, $opaque_id)) {
			return new JSONResponse("",Http::STATUS_OK);
		} else {
			return new JSONResponse([],Http::STATUS_NO_CONTENT);
		}
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
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
	 * @return Http\DataResponse|JSONResponse
	 *
	 * ListSentShares returns the shares created by the user. If md is provided is not nil,
	 * it returns only shares attached to the given resource.
	 */
	public function ListSentShares($userId) {
		$requests = $this->request->getParams();
		$request = array_values($requests)[2];
		$type = $request["type"];
		$term = $request["Term"];
		$creator = $term["Creator"];
		$idpCreator = $creator["idp"];
		$opaqueIdCreator = ["opaque_id"];
		$typeCreator = ["type"];
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
		$spec = $this->request->getParam("Spec");
		$Id = $spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$share = $this->shareProvider->getReceivedShareByOpaqueId($userId,$opaqueId);
		if ($share) {
			$response = $this->shareInfoToResourceInfo($share);
			$response["state"] = 2;
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "GetReceivedShare failed"],Http::STATUS_NO_CONTENT);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
	 *
	 * GetSentShare gets the information for a share by the given ref.
	 */
	public function GetSentShare($userId) {
		$spec = $this->request->getParam("Spec");
		$Id = $spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$share = $this->shareProvider->getSentShareByOpaqueId($userId,$opaqueId);
		if ($share) {
			$response = $this->shareInfoToResourceInfo($share);
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "GetSentShare failed"], Http::STATUS_NO_CONTENT);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @return Http\DataResponse|JSONResponse
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
}
