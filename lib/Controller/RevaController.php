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
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;

class RevaController extends Controller {
	/* @var IURLGenerator */
	private $urlGenerator;

	/* @var ISession */
	private $session;
	
	public function __construct($AppName, IRootFolder $rootFolder, IRequest $request, ISession $session, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IConfig $config, \OCA\ScienceMesh\Service\UserService $UserService, ITrashManager $trashManager) 
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
	}

	private function getFileSystem() {
		// Create the Nextcloud Adapter
		$adapter = new NextcloudAdapter($this->sciencemeshFolder);
		$filesystem = new \League\Flysystem\Filesystem($adapter);
		return $filesystem;
	}

	private function getStorageUrl($userId) {
		$storageUrl = $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkToRoute("sciencemesh.storage.handleHead", array("userId" => $userId, "path" => "foo")));
		$storageUrl = preg_replace('/foo$/', '', $storageUrl);
		return $storageUrl;
	}

	private function initializeStorage($userId) {
		$this->userFolder = $this->rootFolder->getUserFolder($userId);
		if (!$this->userFolder->nodeExists("sciencemesh")) {
			$this->userFolder->newFolder("sciencemesh"); // Create the Sciencemesh directory for storage if it doesn't exist.
		}
		$this->sciencemeshFolder = $this->userFolder->get("sciencemesh");
		$this->filesystem = $this->getFileSystem();
		$this->baseUrl = $this->getStorageUrl($userId);
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
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		// FIXME: Expected a param with a grant to add here;
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function CreateDir($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		$success = $this->filesystem->createDir($path);
		if ($success) {
			return new JSONResponse("OK", 200);
		} else {
			return new JSONResponse(["error" => "Could not create directory."], 500);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function CreateHome($userId) {
		$this->initializeStorage($userId);
		return new JSONResponse("OK", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function CreateReference($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function Delete($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		$success = $this->filesystem->delete($path);
		if ($success) {
			return new JSONResponse("OK", 200);
		} else {
			return new JSONResponse(["error" => "Failed to delete."], 500);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function EmptyRecycle($userId) {
		$this->initializeStorage($userId);
		$user = $this->userManager->get($userId);
		$trashItems = $this->trashManager->listTrashRoot($user);

		$result = [];
		foreach ($trashItems as $node) {
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
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		if ($this->filesystem->has($path)) {
			$metadata = $filesystem->getMetaData($path);
			return new JSONResponse($metadata, 200);
		} else {
			return new JSONResponse(["error" => "File not found"], 404);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function GetPathByID($userId) {
		$this->initializeStorage($userId);
		$storageId = $this->request->getParam("storage_id");
		$opaqueId = $this->request->getParam("opaque_id");
		return new JSONResponse("/foo", 200);
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
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		if ($path == "/") {
			$folderContents = $this->filesystem->listContents(".");
		} else {
			$folderContents = $this->filesystem->listContents($path);
		}
		if ($folderContents !== false) {
			return new JSONResponse($folderContents, 200);
		} else {
			return new JSONResponse(["error" => "Folder not found"], 400);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function ListGrants($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		return new JSONResponse("Not implemented", 200);
	}
	
	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function ListRecycle($userId) {
		$this->initializeStorage($userId);
		$user = $this->userManager->get($userId);
		$trashItems = $this->trashManager->listTrashRoot($user);

		$result = [];
		foreach ($trashItems as $node) {
			if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
				$result[] = [
				    'mimetype' => $node->getMimetype(),
				    'path' => preg_replace("/^sciencemesh/", "", $node->getOriginalLocation()),
				    'size' => $node->getSize(),
				    'basename' => basename($node->getPath()),
				    'timestamp' => $node->getMTime(),
				    'deleted' => $node->getDeletedTime(),
				    'type' => $node->getType(),
				    // @FIXME: Use $node->getPermissions() to set private or public
				    //         as soon as we figure out what Nextcloud permissions mean in this context
				    'visibility' => 'public',
				    /*/
				    'CreationTime' => $node->getCreationTime(),
				    'Etag' => $node->getEtag(),
				    'Owner' => $node->getOwner(),
				    /*/
				];
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
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function Move($userId) {
		$this->initializeStorage($userId);
		$from = $this->request->getParam("from");
		$to = $this->request->getParam("to");
		$success = $this->filesystem->move($from, $to);
		if ($success) {
			return new JSONResponse("OK", 200);
		} else {
			return new JSONResponse(["error" => "Failed to move."], 500);
		}
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function RemoveGrant($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		// FIXME: Expected a grant to remove here;
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function RestoreRecycleItem($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		$this->initializeStorage($userId);
		$user = $this->userManager->get($userId);
		$trashItems = $this->trashManager->listTrashRoot($user);

		foreach ($trashItems as $node) {
			if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
				$nodePath = preg_replace("/^sciencemesh/", "", $node->getOriginalLocation());
				if ($path == $nodePath) {
					$this->trashManager->restoreItem($node);
					return new JSONResponse("OK", 200);
				}
			}
		}
		return new JSONResponse(["error" => "Not found."], 404);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function RestoreRevision($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		// FIXME: Expected a revision param here;
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function SetArbitraryMetadata($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
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
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function UpdateGrant($userId) {
		$this->initializeStorage($userId);
		$path = $this->request->getParam("path") ?: "/";
		// FIXME: Expected a paramater with the grant(s)
		return new JSONResponse("Not implemented", 200);
	}

	/**
	 * @PublicPage
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function Upload($userId, $path) {
		$this->initializeStorage($userId);
		$contents = $this->request->put;
		if ($this->filesystem->has($path)) {
			$success = $this->filesystem->update($path, $contents);
			if ($success) {
				return new JSONResponse("OK", 200);
			} else {
				return new JSONResponse(["error" => "Update failed"], 500);
			}
		} else {
			$success = $this->filesystem->write($path, $contents);
			if ($success) {
				return new JSONResponse("OK", 201);
			} else {
				return new JSONResponse(["error" => "Create failed"], 500);
			}
		}
	}
}
