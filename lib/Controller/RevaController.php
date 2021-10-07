<?php
namespace OCA\ScienceMesh\Controller;

use OCA\ScienceMesh\ServerConfig;
use OCA\ScienceMesh\PlainResponse;
use OCA\ScienceMesh\ResourceServer;
use OCA\ScienceMesh\NextcloudAdapter;

use OCA\Files_Trashbin\Trash\ITrashManager;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\ISession;
use OCP\IConfig;

use OCP\Files\IRootFolder;
use OCP\Files\IHomeStorage;
use OCP\Files\SimpleFS\ISimpleRoot;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;

use OCA\Files_Sharing\Controller\ShareAPIController;

use OCP\Constants;

class RevaController extends Controller {
	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;

 # UserService : unused
	public function __construct($AppName, IRootFolder $rootFolder, IRequest $request, ISession $session, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IConfig $config, \OCA\ScienceMesh\Service\UserService $UserService, ITrashManager $trashManager, ShareAPIController $shareAPIController)
	{
		parent::__construct($AppName, $request);
		require_once(__DIR__.'/../../vendor/autoload.php');
		$this->config = new \OCA\ScienceMesh\ServerConfig($config, $urlGenerator, $userManager);
		$this->rootFolder = $rootFolder;
		$this->request     = $request;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;
		$this->userManager = $userManager;
		$this->trashManager = $trashManager;

		$this->userFolder = $this->rootFolder->getUserFolder($userId);
		// Create the Nextcloud Adapter
		$adapter = new NextcloudAdapter($this->userFolder);
		$this->filesystem = new \League\Flysystem\Filesystem($adapter);

		$this->baseUrl = $this->getStorageUrl($userId); // Where is that used?

		# Share
		$this->shareAPIController = $shareAPIController;

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

	private function nodeInfoToCS3ResourceInfo(array $nodeInfo) : array
	{
		  $path = substr($nodeInfo["path"], strlen("/sciencemesh"));
			$isDirectory = ($nodeInfo["mimetype"] == "directory");
			return [
					"opaque" => [
							"map" => NULL,
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
							"target" => NULL,
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

	# Unused so far
	private function getShareInfo(IShare $share) : array
	{
		return [
			'id' => $share->getFullId(),
			'share_type' => $share->getShareType(),
			'uid_owner' => $share->getSharedBy(),
			'displayname_owner' => $this->userManager->get($share->getSharedBy())->getDisplayName(),
			'permissions' => 0,
			'stime' => $share->getShareTime()->getTimestamp(),
			'parent' => null,
			'expiration' => null,
			'token' => null,
			'uid_file_owner' => $share->getShareOwner(),
			'displayname_file_owner' => $this->userManager->get($share->getShareOwner())->getDisplayName(),
			'path' => $share->getTarget(),
		];
	}

	# For ListReceivedShares, GetReceivedShare and UpdateReceivedShare we need to include "state:2"
	private function shareInfoToResourceInfo(array $share_array): array
	{
		return [
			"id"=>[
	    	"map" => NULL,
			],
			"resource_id"=>[
	    	"map" => NULL,
			],
			"permissions"=>[
				"permissions"=>[
					"add_grant"=>true,
					"create_container"=>true,
					"delete"=>true,
					"get_path"=>true,
					"get_quota"=>true,
					"initiate_file_download"=>true,
					"initiate_file_upload"=>true,
					"list_grants"=>true,
					"list_container"=>true,
					"list_file_versions"=>true,
					"list_recycle"=>true,
					"move"=>true,
					"remove_grant"=>true,
					"purge_recycle"=>true,
					"restore_file_version"=>true,
					"restore_recycle_item"=>true,
					"stat"=>true,
					"update_grant"=>true,
					"deny_grant"=>true
				]
			],
			"grantee"=>[
				"Id"=>[
					"UserId"=>[
						"idp"=>"0.0.0.0:19000",
						"opaque_id"=>"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
						"type"=>1
					]
				]
			],
			"owner"=>[
				"idp"=>"0::.0.0.0:19000",
				"opaque_id"=>"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type"=>1
			],
			"creator"=>[
				"idp"=>"0.0.0.0:19000",
				"opaque_id"=>"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
				"type"=>1
			],
			"ctime"=>[
				"seconds"=>1234567890
			],
			"mtime"=>[
				"seconds"=>1234567890
			]
		];
	}

	# correspondes the permissions we got from Reva to Nextcloud
	private function getPermissionsCode(array $permissions) : int
	{
		$permissionsCode = 0;
		if(!empty($permissions["GetPath"]) || !empty($permissions["GetQuota"]) || !empty($permissions["InitiateFileDownload"]) || !empty($permissions["InitiateFileUpload"]) ||  !empty($permissions["Stat"]) ){
			$permissionsCode += \OCP\Constants::PERMISSION_READ;
		}
		if( !empty($permissions["CreateContainer"]) || !empty($permissions["Move"]) ||  !empty($permissions["AddGrant"]) || !empty($permissions["RestoreFileVersion"]) || !empty($permissions["RestoreRecycleItem:"]) ){
			$permissionsCode += \OCP\Constants::PERMISSION_CREATE;
		}
		if( !empty($permissions["Move"]) || !empty($permissions["Delete"]) || !empty($permissions["RemoveGrant"])){
			$permissionsCode += \OCP\Constants::PERMISSION_DELETE;
		}
		if( !empty($permissions["ListGrants"]) || !empty($permissions["ListContents"]) || !empty($permissions["ListFileVersions"]) || !empty($permissions["ListRecycle"])){
			$permissionsCode += \OCP\Constants::PERMISSION_SHARE;
		}
		if( !empty($permissions["UpdateGrant"])){
			$permissionsCode += \OCP\Constants::PERMISSION_UPDATE;
		}
		return $permissionsCode;
	}

	# correspondes the permissions we got from Reva to Nextcloud
	// private function getSharedWithCode(int $shareWith) : int
	// {
	// 		if($shareWith == ){
	// 			return
	// 		}
	// }
	private function getStorageUrl($userId) {
		$storageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("sciencemesh.storage.handleHead", array("userId" => $userId, "path" => "foo")));
		$storageUrl = preg_replace('/foo$/', '', $storageUrl);
		return $storageUrl;
	}

	private function respond($responseBody, $statusCode, $headers=array()) {
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
		return new JSONResponse("Not implemented", 200);
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
			return new JSONResponse("Logged in", 200);
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
			return new JSONResponse("OK", 200);
		}
		return new JSONResponse(["error" => "Could not create directory."], 500);
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
		return new JSONResponse("OK", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function CreateReference($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: normalize incoming path
		return new JSONResponse("Not implemented", 200);
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
			return new JSONResponse("OK", 200);
		}
		return new JSONResponse(["error" => "Failed to delete."], 500);

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
		return new JSONResponse("OK", 200);
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
			return new JSONResponse($resourceInfo, 200);
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
		return new TextPlainResponse($path, 200);
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
		return new JSONResponse($response, 200);
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
		if(!$success){
			return new JSONResponse(["error" => "Folder not found"], 404);
		}
		$nodeInfos = $this->filesystem->listContents($path);
		$resourceInfos = array_map(function($nodeInfo) {
			return $this->nodeInfoToCS3ResourceInfo($nodeInfo);
		}, $nodeInfos);
		return new JSONResponse($resourceInfos, 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function ListGrants($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented", 200);
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
						"map" => NULL,
					],
					"key" =>  $path,
					"ref"	=> [
						"resource_id" => [
							"map" => NULL,
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
		return new JSONResponse($result, 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function ListRevisions($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented", 200);
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
			return new JSONResponse("OK", 200);
		}
		return new JSONResponse(["error" => "Failed to move."], 500);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function RemoveGrant($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a grant to remove here;
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
//{"key":"some-deleted-version","path":"/","restoreRef":{"path":"/subdirRestored"}}`:
	//{200, ``, serverStateFileRestored},

//{"key":"some-deleted-version","path":"/","restoreRef":null}`:
  //{200, ``, serverStateFileRestored},

	public function RestoreRecycleItem($userId) {
		$key  = $this->request->getParam("key");
		$user = $this->userManager->get($userId);
		$trashItems = $this->trashManager->listTrashRoot($user);

		foreach ($trashItems as $node) {
			if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
				// we are using original location as the RecycleItem's
				// unique key string, see:
				// https://github.com/cs3org/cs3apis/blob/6eab4643f5113a54f4ce4cd8cb462685d0cdd2ef/cs3/storage/provider/v1beta1/resources.proto#L318

				if ("sciencemesh" . $key == $node->getOriginalLocation()) {
					$this->trashManager->restoreItem($node);
					return new JSONResponse("OK", 200);
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
		return new JSONResponse("Not implemented", 200);
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
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function UnsetArbitraryMetadata($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function UpdateGrant($userId) {
		$path = "sciencemesh" . $this->request->getParam("path") ?: "/"; // FIXME: sanitize
		// FIXME: Expected a paramater with the grant(s)
		return new JSONResponse("Not implemented", 200);
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
				return new JSONResponse("OK", 200);
			} else {
				return new JSONResponse(["error" => "Update failed"], 500);
			}
		}
		$success = $this->filesystem->write("/sciencemesh" . $path, $contents);
		if ($success) {
			return new JSONResponse("OK", 201);
		}
		return new JSONResponse(["error" => "Create failed"], 500);
	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * Create a new share in fn with the given access control list.
	 */

    // {
    //   "md":{
    //     "opaque":{},
    //     "type":1,
    //     "id":{
    //       "opaque_id":"fileid-/some/path"
    //     },
    //     "checksum":{},
    //     "etag":"deadbeef",
    //     "mime_type":"text/plain",
    //     "mtime":{
    //       "seconds":1234567890
    //     },
    //     "path":"/some/path",
    //     "permission_set":{},
    //     "size":12345,
    //     "canonical_metadata":{},
    //     "arbitrary_metadata":{
    //       "metadata":{
    //         "da":"ta","some":"arbi","trary":"meta"
    //       }
    //     }
    //   },
    //   "g":{
    //     "grantee":{
    //       "Id":null
    //     },
    //   "permissions":{
    //     "permissions":{}
    //     }
    //   }
    // }
    // SHARE WITH WHO???
    // acl =  {"md":{"opaque":{},"type":1,"id":{"opaque_id":"fileid-/some/path"},"checksum":{},"etag":"deadbeef","mime_type":"text/plain","mtime":{"seconds":1234567890},"path":"/some/path","permission_set":{},"size":12345,"canonical_metadata":{},"arbitrary_metadata":{"metadata":{"da":"ta","some":"arbi","trary":"meta"}}},"g":{"grantee":{"Id":null},"permissions":{"permissions":{}}}}`???

	public function Share($userId){
    $md =  $this->request->getParam("md");
		$g = $this->request->getParam("g");

    $resourceType = $md["type"];
		$resourcePath =  "sciencemesh".$md["path"];
    $resourceId =  $md["id"];
    $resourceOpaqueId = $resourceId["opaque_id"];

		$sharePermissions = $g["permissions"];
		$resourcePermissions = $sharePermissions["permissions"];
		$permissionsCode = $this->getPermissionsCode($resourcePermissions);
		$grantee = $g["grantee"];
		$granteeId = $grantee["Id"];
		$granteeIdUserId = $granteeId["UserId"];
		$granteeType = $granteeIdUserId["type"];
		$shareWith = $granteeIdUserId["opaque_id"]."@".$granteeIdUserId["idp"];
		error_log("GranteeType: ".$granteeType);
		error_log("shareWith: ".$shareWith);

		$success = $this->shareAPIController->createShare($resourcePath,$permissionsCode,$granteeType,$shareWith);
		if($success){
			$response = $this->shareInfoToResourceInfo();
			return new JSONResponse($response, 200);
		}
		return new JSONResponse(["error" => "Share failed"], 500);
	}
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * GetShare gets the information for a share by the given ref.
	 */
	public function GetShare($userId){
		$spec =  $this->request->getParam("Spec");
		$Id = $spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$success = $this->shareAPIController->getShare($opaqueId);
		if($success){
			$response = shareInfoToResourceInfo();
			return new JSONResponse($response, 200);
		}
		return new JSONResponse(["error" => "GetShare failed"], 500);

	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * Unshare deletes the share pointed by ref.
	 */
	public function UnShare($userId){
		$spec =  $this->request->getParam("Spec");
		$Id = $spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$success = $this->shareAPIController->deleteShare($opaqueId);
		if ($success) {
			return new JSONResponse("OK", 201);
		}
		return new JSONResponse(["error" => "UnShare failed"], 500);
	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
	 */
	public function UpdateShare($userId){
    $ref =  $this->request->getParam("ref");
		$Spec = $ref["Spec"];
    $Id = $Spec["Id"];
    $opaqueId = $Id["opaque_id"];
		$p = $ref["p"];
		$permissions = $p["permissions"];
		$success = $this->shareAPIController->updateShare($opaqueId,$permissions); # I do not need to include the rest arguments?
    if($success) {
      $response = shareInfoToResourceInfo();
      return new JSONResponse($response, 201);
    }
    return new JSONResponse(["error" => "UpdateShare failed"], 500);
	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * ListShares returns the shares created by the user. If md is provided is not nil,
 	 * it returns only shares attached to the given resource.
	 */
	public function ListShares($userId){
		$response = shareInfoToResourceInfo();
		$success =  $this->shareAPIController->getShares();
    if ($success) {
      return new JSONResponse('Not Implemented', 201);
    }
    return new JSONResponse(["error" => "ListShares failed"], 500);
	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * ListReceivedShares returns the list of shares the user has access.
	 */
	public function ListReceivedShares($userId){
    // $response = shareInfoToResourceInfo();
    // $response["state"]=>2;
		$success =  $this->shareAPIController->getShares($sharedWithMe = true);
    if ($success) {
      return new JSONResponse('Not Implemented', 201);
    }
    return new JSONResponse(["error" => "ListReceivedShares failed"], 500);

	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * GetReceivedShare returns the information for a received share the user has access.
	 */
# The user has access for what???? ( read, write , unshare??)
	public function GetReceivedShare($userId){
    $spec =  $this->request->getParam("Spec");
    $Id = $spec["Id"];
    $opaqueId = $Id["opaque_id"];
		$success = $this->shareAPIController->getShare($opaqueId,);
    if($success) {
      $response = shareInfoToResourceInfo($success);
      $response["state"] = 2;
      return new JSONResponse($response, 201);
    }
    return new JSONResponse(["error" => "GetReceivedShare failed"], 500);
	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * UpdateReceivedShare updates the received share with share state.
	 */
   # diff with UpdateShare???
   // UpdateReceivedShare {
   //   "ref":{
   //     "Spec":{
   //       "Id":{
   //         "opaque_id":"some-share-id"
   //       }
   //     }
   //   }
   //   ,"f":{
   //     "Field":{
   //       "DisplayName":"some new name for this received share"
   //     }
   //   }
   // }
   // Move the share as a recipient of the share.
   // public moveShare(IShare $share, string $recipientId) : IShare
   // This is updating the share target. So where the recipient has the share mounted.

   // Use moveshare???
	public function UpdateReceivedShare($userId){
    $spec =  $this->request->getParam("Spec");
    $Id = $spec["Id"];
    $opaqueId = $Id["opaque_id"];
		$success = $this->shareAPIController->updateShare($opaqueId,$permissions);
    if ($success) {
      // $response = shareInfoToResourceInfo();
      // $response["state"]=2;
      return new JSONResponse("Not Implemented", 201);
    }
    return new JSONResponse(["error" => "UpdateReceivedShare failed"], 500);
  }
}
