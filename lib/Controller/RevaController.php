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

use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

use OCP\Share\Exceptions;
use OCP\Constants;

class RevaController extends Controller {
	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;

 # UserService : unused
	public function __construct($AppName, IRootFolder $rootFolder, IRequest $request, ISession $session, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IConfig $config, \OCA\ScienceMesh\Service\UserService $UserService, ITrashManager $trashManager, IManager $shareManager)
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
		$this->shareManager = $shareManager;

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

	# For ListReceivedShares, GetReceivedShare and UpdateReceivedShare we need to include "state:2"
	private function shareInfoToResourceInfo(IShare $share): array
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
		if(!empty($permissions["get_path"]) || !empty($permissions["get_quota"]) || !empty($permissions["initiate_file_download"]) || !empty($permissions["initiate_file_upload"]) ||  !empty($permissions["stat"]) ){
			$permissionsCode += \OCP\Constants::PERMISSION_READ;
		}
		if( !empty($permissions["create_container"]) || !empty($permissions["move"]) ||  !empty($permissions["add_grant"]) || !empty($permissions["restore_file_version"]) || !empty($permissions["restore_recycle_item"]) ){
			$permissionsCode += \OCP\Constants::PERMISSION_CREATE;
		}
		if( !empty($permissions["move"]) || !empty($permissions["delete"]) || !empty($permissions["remove_grant"])){
			$permissionsCode += \OCP\Constants::PERMISSION_DELETE;
		}
		if( !empty($permissions["list_grants"]) || !empty($permissions["list_file_versions"]) || !empty($permissions["list_recycle"])){
			$permissionsCode += \OCP\Constants::PERMISSION_SHARE;
		}
		if( !empty($permissions["update_grant"])){
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
// 	"md":{
// 		"opaque_id":"fileid-/some/path"
// 	},
// 	"g":{
// 		"grantee":{
// 			"Id":{
// 				"UserId":{
// 					"idp":"0.0.0.0:19000",
// 					"opaque_id":"f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
// 					"type":1
// 				}
// 			}
// 		},
// 		"permissions":{
// 			"permissions":{}
// 			}
// 		}
// 	}
	public function Share($userId){
    $md =  $this->request->getParam("md");
		$g = $this->request->getParam("g");
		$opaqueId = $md["opaque_id"];
		$opaqueId = str_replace('fileid-', 'sciencemesh', $opaqueId);
		$grantee = $g["grantee"];
		$granteeId = $grantee["Id"];
		$granteeIdUserId = $granteeId["UserId"];
//		$shareWith = $granteeIdUserId["opaque_id"]."@".$granteeIdUserId["idp"];
		$sharePermissions = $g["permissions"];

		$resourcePermissions = $sharePermissions["permissions"];
		$permissionsCode = $this->getPermissionsCode($resourcePermissions);
		//$shareWith = "einstein@localhost:8080";
		$shareWith = "einstein@example.com";
		$share = $this->shareManager->newShare();
		$share->setPermissions($permissionsCode);
		$share->setShareType(IShare::TYPE_REMOTE);
		$share->setSharedBy($userId);
		try {
		$path = $this->userFolder->get($opaqueId);
		} catch (NotFoundException $e) {
			return new JSONResponse(["error" => "Share failed. Resource Path not found"], 500);
		}
		$share->setNode($path);
		try {
			$share->setSharedWith($shareWith);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(["error" => "Share failed. Invalid share receipient"], 500);}
		$this->shareManager->createShare($share);
		$response = $this->shareInfoToResourceInfo($share);
		return new JSONResponse($response, 201);
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
		$share = $this->shareManager->getShareById($opaqueId);
		if($share){
			$response = $this->shareInfoToResourceInfo($share);
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
	public function Unshare($userId){
		$spec =  $this->request->getParam("Spec");
		$Id = $spec["Id"];
		$opaqueId = $Id["opaque_id"];
		$share = $this->shareManager->getShareById($opaqueId);
		$success = $this->shareManager->deleteShare($share);
		if ($success) {
			return new JSONResponse("OK", 201);
		}
		return new JSONResponse(["error" => "Unshare failed"], 500);
	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
	 */

	public function UpdateShare($userId){
    $ref =  $this->request->getParam("ref");
		$spec = $ref["Spec"];
    $id = $spec["Id"];
    $opaqueId = $id["opaque_id"];
		$p = $this->request->getParam("p");
		$permissions = $p["permissions"];
		$permissionsCode = $this->getPermissionsCode($permissions);
		$share = $this->shareManager->getShareById($opaqueId);
		$updated = $this->shareManager->updateShare($share, $permissionsCode);
    if($updated) {
      $response = $this->shareInfoToResourceInfo($updated);
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
		$requests = $this->request->getParams();
		$request = array_values($requests)[2];
		$type = $request["type"];
		$term =  $request["Term"];
		$creator = $term["Creator"];
		$idpCreator = $creator["idp"];
		$opaqueIdCreator = ["opaque_id"];
		$typeCreator = ["type"];
		$responses = [];
		$shares =  $this->shareManager->getSharesBy($userId, 6);
    if ($shares) {
			foreach ($shares as $share) {
				array_push($responses,$this->shareInfoToResourceInfo($share));
			}
      return new JSONResponse($responses, 201);
    }
    return new JSONResponse([], 200);
	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * ListReceivedShares returns the list of shares the user has access.
	 */
	public function ListReceivedShares($userId){
		$requests = $this->request->getParams();
		$request = array_values($requests)[2];
		$type = $request["type"];
		$term =  $request["Term"];
		$creator = $term["Creator"];
		$idpCreator = $creator["idp"];
		$opaqueIdCreator = ["opaque_id"];
		$typeCreator = ["type"];

		$responses = [];
		$shares =  $this->shareManager->getSharedWith($userId,IShare::TYPE_REMOTE);
    if ($shares) {
			foreach ($shares as $share) {
				$response = $this->shareInfoToResourceInfo($share);
				$response["state"] = 2;
				array_push($responses, $response);
			}
      return new JSONResponse($responses, 201);
    }
    return new JSONResponse([], 200);

	}
  /**
	 * @PublicPage
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
   *
   * GetReceivedShare returns the information for a received share the user has access.
	 */
	public function GetReceivedShare($userId){
    $spec =  $this->request->getParam("Spec");
    $Id = $spec["Id"];
    $opaqueId = $Id["opaque_id"];
		$share = $this->shareManager->getShareById($opaqueId,$userId);
    if($share) {
      $response = $this->shareInfoToResourceInfo($share);
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
	public function UpdateReceivedShare($userId){
		$ref =  $this->request->getParam("ref");
		$Spec = $ref["Spec"];
    $Id = $Spec["Id"];
    $opaqueId = $Id["opaque_id"];
		$share = $this->shareManager->getShareById($opaqueId,$userId);
		$updated = $this->shareManager->updateShare($share, 5);
    if($updated) {
      $response = $this->shareInfoToResourceInfo($updated);
			$response["state"] = 2;
      return new JSONResponse($response, 201);
    }
    return new JSONResponse(["error" => "UpdateReceivedShare failed"], 500);
	}
}
