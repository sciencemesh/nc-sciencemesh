<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\Controller;

use Exception;
use OC\Config;
use OC\Files\View;
use OCA\DAV\TrashBin\TrashBinManager;
use OCA\ScienceMesh\ServerConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

define("RESTRICT_TO_SCIENCEMESH_FOLDER", false);
define("EFSS_PREFIX", (RESTRICT_TO_SCIENCEMESH_FOLDER ? "sciencemesh/" : ""));

// See https://github.com/pondersource/sciencemesh-php/issues/96#issuecomment-1298656896
define("REVA_PREFIX", "/home/");

class StorageController extends Controller
{
    /** @var IUserManager */
    private IUserManager $userManager;

    /** @var Config */
    private $config;

    /** @var IRootFolder */
    private IRootFolder $rootFolder;

    /** @var TrashBinManager */
    private TrashBinManager $trashManager;

    /** @var string */
    private string $userId;

    /** @var Folder */
    private Folder $userFolder;

    /** @var IL10N */
    private IL10N $l;

    /** @var ILogger */
    private ILogger $logger;

    /**
     * Storage Controller.
     *
     * @param string $appName
     * @param IRootFolder $rootFolder
     * @param IRequest $request
     * @param IUserManager $userManager
     * @param IConfig $config
     * @param TrashBinManager $trashManager
     * @param IL10N $l10n
     * @param ILogger $logger
     */
    public function __construct(
        string          $appName,
        IRootFolder     $rootFolder,
        IRequest        $request,
        IUserManager    $userManager,
        IConfig         $config,
        TrashBinManager $trashManager,
        IL10N           $l10n,
        ILogger         $logger
    )
    {
        parent::__construct($appName, $request);
        require_once(__DIR__ . "/../../vendor/autoload.php");

        $this->rootFolder = $rootFolder;
        $this->request = $request;
        $this->userManager = $userManager;
        $this->config = new ServerConfig($config);
        $this->trashManager = $trashManager;
        $this->l = $l10n;
        $this->logger = $logger;
    }

    /**
     * @throws NotPermittedException
     * @throws Exception
     */
    private function init($userId)
    {
        error_log("RevaController init for user '$userId'");
        $this->userId = $userId;
        $this->checkRevadAuth();
        if ($userId) {
            error_log("root folder absolute path '" . $this->rootFolder->getPath() . "'");
            if ($this->rootFolder->nodeExists($userId)) {
                $this->userFolder = $this->rootFolder->getUserFolder($userId);
                error_log("user folder '" . $this->userFolder->getPath() . "'");
            } else {
                throw new Exception("Home folder not found for user '$userId', have they logged in through the ownCloud web interface yet?");
            }
        }
    }

    /**
     * @throws NotPermittedException
     * @throws Exception
     */
    private function checkRevadAuth()
    {
        error_log("checkRevadAuth");
        $authHeader = $this->request->getHeader('X-Reva-Secret');

        if ($authHeader != $this->config->getRevaSharedSecret()) {
            throw new NotPermittedException('Please set an http request header "X-Reva-Secret: <your_shared_secret>"!');
        }
    }

    private function efssFullPathToRelativePath($efssFullPath)
    {

        $ret = $this->removePrefix($efssFullPath, $this->userFolder->getPath());
        error_log("efssFullPathToRelativePath: Interpreting $efssFullPath as $ret");
        return $ret;
    }

    private function efssPathToRevaPath($efssPath): string
    {
        $ret = REVA_PREFIX . $this->removePrefix($efssPath, EFSS_PREFIX);
        error_log("efssPathToRevaPath: Interpreting $efssPath as $ret");
        return $ret;
    }

    private function revaPathToEfssPath($revaPath): string
    {
        if ("$revaPath/" == REVA_PREFIX) {
            error_log("revaPathToEfssPath: Interpreting special case $revaPath as ''");
            return '';
        }
        $ret = EFSS_PREFIX . $this->removePrefix($revaPath, REVA_PREFIX);
        error_log("revaPathToEfssPath: Interpreting $revaPath as $ret");
        return $ret;
    }

    private function revaPathFromOpaqueId($opaqueId)
    {
        return $this->removePrefix($opaqueId, "fileid-");
    }

    private function removePrefix($string, $prefix)
    {
        // first check if string is actually prefixed or not.
        $len = strlen($prefix);
        if (substr($string, 0, $len) === $prefix) {
            $ret = substr($string, $len);
        } else {
            $ret = $string;
        }

        return $ret;
    }

    private function getChecksum(Node $node, $checksumType = 4): string
    {
        $checksumTypes = array(
            1 => "UNSET:",
            2 => "ADLER32:",
            3 => "MD5:",
            4 => "SHA1:",
        );

        // checksum is in db table oc_filecache.
        // folders do not have checksum
        $checksums = explode(" ", $node->getFileInfo()->getChecksum());

        foreach ($checksums as $checksum) {

            // NOTE: that the use of !== false is deliberate (neither != false nor === true will return the desired result);
            // strpos() returns either the offset at which the needle string begins in the haystack string, or the boolean
            // false if the needle isn't found. Since 0 is a valid offset and 0 is "false", we can't use simpler constructs
            //  like !strpos($a, 'are').
            if (strpos($checksum, $checksumTypes[$checksumType]) !== false) {
                return substr($checksum, strlen($checksumTypes[$checksumType]));
            }
        }

        return "";
    }

    /**
     * @throws InvalidPathException
     * @throws NotFoundException
     */
    private function nodeToCS3ResourceInfo(Node $node): array
    {
        $isDirectory = ($node->getType() === FileInfo::TYPE_FOLDER);
        $efssPath = substr($node->getPath(), strlen($this->userFolder->getPath()) + 1);
        $revaPath = $this->efssPathToRevaPath($efssPath);

        $payload = [
            "type" => ($isDirectory ? 2 : 1),
            "id" => [
                "opaque_id" => "fileid-" . $revaPath,
            ],
            "checksum" => [
                // checksum algorithms:
                // 1 UNSET
                // 2 ADLER32
                // 3 MD5
                // 4 SHA1

                // NOTE: folders do not have checksum, their type should be unset.
                "type" => $isDirectory ? 1 : 4,
                "sum" => $this->getChecksum($node, $isDirectory ? 1 : 4),
            ],
            "etag" => $node->getEtag(),
            "mime_type" => ($isDirectory ? "folder" : $node->getMimetype()),
            "mtime" => [
                "seconds" => $node->getMTime(),
            ],
            "path" => $revaPath,
            "permissions" => $node->getPermissions(),
            "size" => $node->getSize(),
            "owner" => [
                "opaque_id" => $this->userId,
                "idp" => $this->config->getIopIdp(),
            ]
        ];

        error_log("nodeToCS3ResourceInfo " . var_export($payload, true));

        return $payload;
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function addGrant($userId): JSONResponse
    {
        error_log("AddGrant");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));
        // FIXME: Expected a param with a grant to add here;

        return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function createDir($userId): JSONResponse
    {
        error_log("CreateDir");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $urlDecodedPath = urldecode($this->request->getParam("path"));
        $path = $this->revaPathToEfssPath($urlDecodedPath);

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
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function createHome($userId): JSONResponse
    {
        error_log("CreateHome");
        if (RESTRICT_TO_SCIENCEMESH_FOLDER) {
            if ($this->userManager->userExists($userId)) {
                $this->init($userId);
            } else {
                return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
            }
            $homeExists = $this->userFolder->nodeExists("sciencemesh");
            if (!$homeExists) {
                try {
                    $this->userFolder->newFolder("sciencemesh"); // Create the Sciencemesh directory for storage if it doesn't exist.
                } catch (NotPermittedException $e) {
                    return new JSONResponse(["error" => "Create home failed. Resource Path not founD"], Http::STATUS_INTERNAL_SERVER_ERROR);
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
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function createReference($userId): JSONResponse
    {
        error_log("CreateReference");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }
        $path = $this->revaPathToEfssPath($this->request->getParam("path"));
        return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
    }

    // TODO: @Mahdi maybe not used anymore.

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     */
    public function createStorageSpace($userId): JSONResponse
    {
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
     * @param $userId
     * @return JSONResponse
     * @throws NotFoundException
     * @throws NotPermittedException
     */
    public function delete($userId): JSONResponse
    {
        error_log("Delete");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));

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
     * @return JSONResponse|StreamResponse
     * @throws NotFoundException|NotPermittedException
     */
    public function download($userId, $path)
    {
        error_log("Download");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        error_log("Download path: $path");

        $efssPath = $this->removePrefix($path, "home/");
        error_log("Download efss path: $efssPath");

        if ($this->userFolder->nodeExists($efssPath)) {
            error_log("Download: file found");
            $node = $this->userFolder->get($efssPath);
            $view = new View();
            $nodeLocalFilePath = $view->getLocalFile($node->getPath());
            error_log("Download local file path: $nodeLocalFilePath");
            return new StreamResponse($nodeLocalFilePath);
        }

        error_log("Download: file not found");
        return new JSONResponse(["error" => "File not found"], 404);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function emptyRecycle($userId): JSONResponse
    {
        // TODO: @Mahdi fix this! DIFFERENT FUNCTION IN NC/OC
        error_log("EmptyRecycle");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        // See https://github.com/sciencemesh/oc-sciencemesh/issues/4#issuecomment-1283542906
        $this->trashManager->deleteAll();
        return new JSONResponse("OK", Http::STATUS_OK);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotFoundException
     * @throws NotPermittedException|InvalidPathException
     * @throws Exception
     */
    public function getMD($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $ref = $this->request->getParam("ref");
        error_log("GetMD " . var_export($ref, true));

        if (!isset($ref)) {
            return new JSONResponse("ref not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        if (isset($ref["path"])) {
            // e.g. GetMD:
            // {
            //  "ref": {
            //    "path": "/home/asdf"
            //  },
            //  "mdKeys": null
            // }
            $revaPath = $ref["path"];
        } else if (
            isset($ref["resource_id"]["opaque_id"])
            &&
            str_starts_with($ref["resource_id"]["opaque_id"], "fileid-")
        ) {
            // e.g. GetMD:
            // {
            //  "ref": {
            //    "resource_id": {
            //      "storage_id": "00000000-0000-0000-0000-000000000000",
            //      "opaque_id": "fileid-/asdf"
            //    }
            //  },
            //  "mdKeys": null
            // }
            $revaPath = $this->revaPathFromOpaqueId($ref["resource_id"]["opaque_id"]);
        } else {
            return new JSONResponse("ref not understood!", Http::STATUS_BAD_REQUEST);
        }

        // this path is url coded, we need to decode it
        // for example this converts "we%20have%20space" to "we have space"
        $revaPathDecoded = urldecode($revaPath);

        $path = $this->revaPathToEfssPath($revaPathDecoded);
        error_log("Looking for EFSS path '$path' in user folder; reva path '$revaPathDecoded' ");

        // apparently nodeExists requires relative path to the user folder:
        // see https://github.com/owncloud/core/blob/b7bcbdd9edabf7d639b4bb42c4fb87862ddf4a80/lib/private/Files/Node/Folder.php#L45-L55;
        // another string manipulation is necessary to extract relative path from full path.
        $relativePath = $this->efssFullPathToRelativePath($path);

        $success = $this->userFolder->nodeExists($relativePath);
        if ($success) {
            error_log("File found");
            $node = $this->userFolder->get($relativePath);
            $resourceInfo = $this->nodeToCS3ResourceInfo($node);
            return new JSONResponse($resourceInfo, Http::STATUS_OK);
        }

        error_log("File not found");
        return new JSONResponse(["error" => "File not found"], 404);
    }

    // TODO: @Mahdi remove.

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @return Http\DataResponse|JSONResponse
     * @throws NotPermittedException
     */
    public function getPathByID($userId)
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        // TODO: @Mahdi what is "in progress"? what should be done here?
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
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function initiateUpload($userId): JSONResponse
    {
        $ref = $this->request->getParam("ref");
        $path = $this->revaPathToEfssPath(($ref["path"] ?? ""));

        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }
        $response = [
            "simple" => $path
        ];

        return new JSONResponse($response, Http::STATUS_OK);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws InvalidPathException
     * @throws NotFoundException
     * @throws NotPermittedException
     */
    public function listFolder($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $ref = $this->request->getParam("ref");

        // this path is url coded, we need to decode it
        // for example this converts "we%20have%20space" to "we have space"
        $pathDecoded = urldecode(($ref["path"] ?? ""));
        $path = $this->revaPathToEfssPath($pathDecoded);
        $success = $this->userFolder->nodeExists($path);
        error_log("ListFolder: $path");

        if (!$success) {
            error_log("ListFolder: path not found");
            return new JSONResponse(["error" => "Folder not found"], 404);
        }
        error_log("ListFolder: path found");

        $node = $this->userFolder->get($path);
        $nodes = $node->getDirectoryListing();
        $resourceInfos = array_map(function (Node $node) {
            return $this->nodeToCS3ResourceInfo($node);
        }, $nodes);

        return new JSONResponse($resourceInfos, Http::STATUS_OK);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function listGrants($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));

        return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function listRecycle($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $user = $this->userManager->get($userId);
        $trashItems = $this->trashManager->listTrashRoot($user);
        $result = [];

        foreach ($trashItems as $node) {
            if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
                $path = $this->efssPathToRevaPath($node->getOriginalLocation());
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
                    ]
                ];
            }
        }

        return new JSONResponse($result, Http::STATUS_OK);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function listRevisions($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));

        return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
    }

    # TODO: @Mahdi where is Move endpoint? not implemented?

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function removeGrant($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));
        // FIXME: Expected a grant to remove here;

        return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function restoreRecycleItem($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $key = $this->request->getParam("key");
        $user = $this->userManager->get($userId);
        $trashItems = $this->trashManager->listTrashRoot($user);

        foreach ($trashItems as $node) {
            if (preg_match("/^sciencemesh/", $node->getOriginalLocation())) {
                // we are using original location as the RecycleItem's
                // unique key string, see:
                // https://github.com/cs3org/cs3apis/blob/6eab4643f5113a54f4ce4cd8cb462685d0cdd2ef/cs3/storage/provider/v1beta1/resources.proto#L318

                if ($this->revaPathToEfssPath($key) == $node->getOriginalLocation()) {
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
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function restoreRevision($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));
        // FIXME: Expected a revision param here;

        return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function setArbitraryMetadata($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));
        $metadata = $this->request->getParam("metadata");

        // FIXME: this needs to be implemented for real, merging the incoming metadata with the existing ones.
        // For now we return OK to let the uploads go through, see https://github.com/sciencemesh/nc-sciencemesh/issues/43
        return new JSONResponse("I'm cheating", Http::STATUS_OK);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function unsetArbitraryMetadata($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));

        // FIXME: this needs to be implemented for real
        return new JSONResponse("I'm cheating", Http::STATUS_OK);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function updateGrant($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $path = $this->revaPathToEfssPath($this->request->getParam("path"));

        // FIXME: Expected a parameter with the grant(s)
        return new JSONResponse("Not implemented", Http::STATUS_NOT_IMPLEMENTED);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @param $path
     * @return JSONResponse
     */
    public function upload($userId, $path): JSONResponse
    {
        $revaPath = "/$path";
        error_log("RevaController Upload! user: $userId , reva path: $revaPath");

        try {
            if ($this->userManager->userExists($userId)) {
                $this->init($userId);
            } else {
                return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
            }

            $contents = file_get_contents('php://input');
            $efssPath = $this->revaPathToEfssPath($revaPath);

            error_log("Uploading! reva path: $revaPath");
            error_log("Uploading! efss path $efssPath");

            if ($this->userFolder->nodeExists($efssPath)) {
                $node = $this->userFolder->get($efssPath);
                $view = new View();
                $view->file_put_contents($node->getPath(), $contents);
                return new JSONResponse("OK", Http::STATUS_OK);
            } else {
                $dirname = dirname($efssPath);
                $filename = basename($efssPath);

                if (!$this->userFolder->nodeExists($dirname)) {
                    $this->userFolder->newFolder($dirname);
                }

                $node = $this->userFolder->get($dirname);
                $node->newFile($filename);

                $node = $this->userFolder->get($efssPath);
                $view = new View();
                $view->file_put_contents($node->getPath(), $contents);

                return new JSONResponse("CREATED", Http::STATUS_CREATED);
            }
        } catch (Exception $e) {
            return new JSONResponse(["error" => "Upload failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }
}