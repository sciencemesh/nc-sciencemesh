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

class OcmController extends Controller {

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
		error_log("OcmController init");
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
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 *
	 * Get user list.
	 */
	public function GetUser($dummy) {
		$this->init(false);

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
		error_log("addSentShare");
		$this->init($userId);
		$params = $this->request->getParams();
		$owner = $params["owner"]["opaqueId"]; // . "@" . $params["owner"]["idp"];
		$name = $params["name"]; // "fileid-/other/q/f gr"
		$resourceOpaqueId = $params["resourceId"]["opaqueId"]; // "fileid-/other/q/f gr"
		$revaPath = $this->getRevaPathFromOpaqueId($resourceOpaqueId); // "/other/q/f gr"
		$nextcloudPath = $this->revaPathToNextcloudPath($revaPath);

		$revaPermissions = $params["permissions"]["permissions"]; // {"getPath":true, "initiateFileDownload":true, "listContainer":true, "listFileVersions":true, "stat":true}
		$granteeType = $params["grantee"]["type"]; // "GRANTEE_TYPE_USER"
		$granteeHost = $params["grantee"]["userId"]["idp"]; // "revanc2.docker"
		$granteeUser = $params["grantee"]["userId"]["opaqueId"]; // "marie"

		$nextcloudPermissions = $this->getPermissionsCode($revaPermissions);
		$shareWith = $granteeUser."@".$granteeHost;
		$sharedSecretBase64 = $params["grantee"]["opaque"]["map"]["sharedSecret"]["value"];
		$sharedSecret = base64_decode($sharedSecretBase64);
		error_log("base64 decoded $sharedSecretBase64 to $sharedSecret");
		try {
			$node = $this->userFolder->get($nextcloudPath);
		} catch (NotFoundException $e) {
			return new JSONResponse(["error" => "Share failed. Resource Path not found"], Http::STATUS_BAD_REQUEST);
		}
		error_log("calling newShare");
		$share = $this->shareManager->newShare();
		$share->setNode($node);
		try {
			$this->lock($share->getNode());
		} catch (LockedException $e) {
			throw new OCSNotFoundException($this->l->t('Could not create share'));
		}
		$share->setShareType(IShare::TYPE_REMOTE);//IShare::TYPE_SCIENCEMESH);
		$share->setSharedBy($userId);
		$share->setSharedWith($shareWith);
		$share->setShareOwner($owner);
		$share->setPermissions($nextcloudPermissions);
		$share->setToken($sharedSecret);
		error_log("calling createInternal");
		$share = $this->shareProvider->createInternal($share);
		// $response = $this->shareInfoToCs3Share($share);
		// error_log("response:" . json_encode($response));
		return new DataResponse($share->getId(), Http::STATUS_CREATED);
	}

	/**
	 * add a received share
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 * @return Http\DataResponse|JSONResponse
	 */
	public function addReceivedShare($userId) {
		error_log("addReceivedShare");
		$params = $this->request->getParams();
		$shareData = [
			"remote" => $params["share"]["owner"]["idp"], // FIXME: 'nc1.docker' -> 'https://nc1.docker/'
			"remote_id" =>  base64_decode($params["share"]["grantee"]["opaque"]["map"]["remoteShareId"]["value"]), // FIXME: $this->shareProvider->createInternal($share) suppresses, so not getting an id there, see https://github.com/pondersource/sciencemesh-nextcloud/issues/57#issuecomment-1002143104
			"share_token" => base64_decode($params["share"]["grantee"]["opaque"]["map"]["sharedSecret"]["value"]), // 'tDPRTrLI4hE3C5T'
			"password" => "",
			"name" => rtrim($params["share"]["name"], "/"), // '/grfe'
			"owner" => $params["share"]["owner"]["opaqueId"], // 'einstein'
			"user" => $userId // 'marie'
		];
		$this->init($userId);
		
		$scienceMeshData = [
			"is_external" => true,
		];
		
		$id = $this->shareProvider->addScienceMeshShare($scienceMeshData,$shareData);
		return new JSONResponse($id, 201);
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
		error_log("Unshare");
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
		error_log("UpdateSentShare");
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
		$response = $this->shareInfoToCs3Share($shareUpdated);
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
		error_log("UpdateReceivedShare");
		$this->init($userId);
		$response = [];
		$resourceId = $this->request->getParam("received_share")["share"]["resource_id"];
		$permissions = $this->request->getParam("received_share")["share"]["permissions"];
		$permissionsCode = $this->getPermissionsCode($permissions);
		try {
			$share = $this->shareProvider->getReceivedShareByToken($resourceId);
			$share->setPermissions($permissionsCode);
			$shareUpdate = $this->shareProvider->UpdateReceivedShare($share);
			$response = $this->shareInfoToCs3Share($shareUpdate);
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
		error_log("ListSentShares");
		$this->init($userId);
		$responses = [];
		$shares = $this->shareProvider->getSentShares($userId);
		if ($shares) {
			foreach ($shares as $share) {
				array_push($responses, $this->shareInfoToCs3Share($share));
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
		error_log("ListReceivedShares");
		$this->init($userId);
		$responses = [];
		$shares = $this->shareProvider->getReceivedShares($userId);
		if ($shares) {
			foreach ($shares as $share) {
				$response = $this->shareInfoToCs3Share($share);
				array_push($responses,[
					"share" => $response,
					"state" => 2
				]);
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
		error_log("GetReceivedShare");
		$this->init($userId);
		$opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
		$name = $this->getNameByOpaqueId($opaqueId);
		try {
			$share = $this->shareProvider->getReceivedShareByToken($opaqueId);
			$response = $this->shareInfoToCs3Share($share);
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
		error_log("GetSentShare");
		$this->init($userId);
		$opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
		$name = $this->getNameByOpaqueId($opaqueId);
		$share = $this->shareProvider->getSentShareByName($userId,$name);
		if ($share) {
			$response = $this->shareInfoToCs3Share($share);
			return new JSONResponse($response, Http::STATUS_OK);
		}
		return new JSONResponse(["error" => "GetSentShare failed"], Http::STATUS_BAD_REQUEST);
	}
}
