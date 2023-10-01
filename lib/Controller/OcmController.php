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

use DateTime;
use Exception;
use OC\Config;
use OCA\ScienceMesh\AppInfo\ScienceMeshApp;
use OCA\ScienceMesh\ServerConfig;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Constants;
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
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\IllegalIDChangeException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

define("RESTRICT_TO_SCIENCEMESH_FOLDER", false);
define("EFSS_PREFIX", (RESTRICT_TO_SCIENCEMESH_FOLDER ? "sciencemesh/" : ""));

// See https://github.com/pondersource/sciencemesh-php/issues/96#issuecomment-1298656896
define("REVA_PREFIX", "/home/");

class OcmController extends Controller
{
    /** @var IUserManager */
    private IUserManager $userManager;

    /** @var Config */
    private $config;

    /** @var IRootFolder */
    private IRootFolder $rootFolder;

    /** @var IManager */
    private IManager $shareManager;

    /** @var ScienceMeshShareProvider */
    private ScienceMeshShareProvider $shareProvider;

    /** @var Folder */
    private Folder $userFolder;

    /** @var IL10N */
    private IL10N $l;

    /** @var ILogger */
    private ILogger $logger;

    /**
     * Open Cloud Mesh (OCM) Controller.
     *
     * @param string $appName
     * @param IRootFolder $rootFolder
     * @param IRequest $request
     * @param IUserManager $userManager
     * @param IConfig $config
     * @param IManager $shareManager
     * @param IL10N $l10n
     * @param ILogger $logger
     * @param ScienceMeshShareProvider $shareProvider
     */
    public function __construct(
        string                   $appName,
        IRootFolder              $rootFolder,
        IRequest                 $request,
        IUserManager             $userManager,
        IConfig                  $config,
        IManager                 $shareManager,
        IL10N                    $l10n,
        ILogger                  $logger,
        ScienceMeshShareProvider $shareProvider
    )
    {
        parent::__construct($appName, $request);
        require_once(__DIR__ . "/../../vendor/autoload.php");

        $this->rootFolder = $rootFolder;
        $this->request = $request;
        $this->userManager = $userManager;
        $this->config = new ServerConfig($config);
        $this->shareManager = $shareManager;
        $this->l = $l10n;
        $this->logger = $logger;
        $this->shareProvider = $shareProvider;
    }

    /**
     * @throws NotPermittedException
     * @throws Exception
     */
    private function init($userId)
    {
        error_log("RevaController init for user '$userId'");
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

    // TODO: @Mahdi Move to utils.

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

    // TODO: @Mahdi Move to utils.
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

    // TODO: @Mahdi Move to utils.
    private function revaPathFromOpaqueId($opaqueId)
    {
        return $this->removePrefix($opaqueId, "fileid-");
    }

    // TODO: @Mahdi Move to utils.
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

    // TODO: @Mahdi Move to utils.
    private function getPermissionsCode(array $permissions): int
    {
        $permissionsCode = 0;
        if (!empty($permissions["get_path"]) || !empty($permissions["get_quota"]) || !empty($permissions["initiate_file_download"]) || !empty($permissions["initiate_file_upload"]) || !empty($permissions["stat"])) {
            $permissionsCode += Constants::PERMISSION_READ;
        }
        if (!empty($permissions["create_container"]) || !empty($permissions["move"]) || !empty($permissions["add_grant"]) || !empty($permissions["restore_file_version"]) || !empty($permissions["restore_recycle_item"])) {
            $permissionsCode += Constants::PERMISSION_CREATE;
        }
        if (!empty($permissions["move"]) || !empty($permissions["delete"]) || !empty($permissions["remove_grant"])) {
            $permissionsCode += Constants::PERMISSION_DELETE;
        }
        if (!empty($permissions["list_grants"]) || !empty($permissions["list_file_versions"]) || !empty($permissions["list_recycle"])) {
            $permissionsCode += Constants::PERMISSION_SHARE;
        }
        if (!empty($permissions["update_grant"])) {
            $permissionsCode += Constants::PERMISSION_UPDATE;
        }
        return $permissionsCode;
    }

    // TODO: @Mahdi Move to utils.

    /**
     * @param Node $node
     * @return void
     *
     * @throws LockedException
     */
    private function lock(Node $node)
    {
        $node->lock(ILockingProvider::LOCK_SHARED);
    }

    // TODO: @Mahdi Move to utils.
    // For ListReceivedShares, GetReceivedShare and UpdateReceivedShare we need to include "state:2"
    // see:
    // https://github.com/cs3org/cs3apis/blob/cfd1ad29fdf00c79c2a321de7b1a60d0725fe4e8/cs3/sharing/ocm/v1beta1/resources.proto#L160
    /**
     * @throws NotFoundException
     * @throws InvalidPathException
     */
    private function shareInfoToCs3Share(IShare $share, string $direction, $token = ""): array
    {
        $shareId = $share->getId();

        // TODO @Mahdi use enums!
        if ($direction === "sent") {
            $ocmShareData = $this->shareProvider->getSentOcmShareFromSciencemeshTable($shareId);
            $ocmShareProtocols = $this->shareProvider->getSentOcmShareProtocolsFromSciencemeshTable($ocmShareData["id"]);
        } elseif ($direction === "received") {
            $ocmShareData = $this->shareProvider->getReceivedOcmShareFromSciencemeshTable($shareId);
            $ocmShareProtocols = $this->shareProvider->getReceivedOcmShareProtocolsFromSciencemeshTable($ocmShareData["id"]);
        }

        // use ocm payload stored in sciencemesh table. if it fails, use native efss share data.
        // in case of total failure use "unknown".

        // this one is obvious right?
        if (isset($ocmShareData["share_with"])) {
            $granteeParts = explode("@", $ocmShareData["share_with"]);
        } else {
            $granteeParts = explode("@", $share->getSharedWith());
        }

        if (count($granteeParts) != 2) {
            $granteeParts = ["unknown", "unknown"];
        }

        // the original share owner (who owns the path that is shared)
        if (isset($ocmShareData["owner"])) {
            $ownerParts = explode("@", $ocmShareData["owner"]);
        } else {
            $ownerParts = explode("@", $share->getShareOwner());
        }

        if (count($granteeParts) != 2) {
            $ownerParts = ["unknown", "unknown"];
        }

        // NOTE: @Mahdi initiator/creator/sharedBy etc., whatever other names it has! means the share sharer!
        // you can be owner and sharer, you can be someone who is re-sharing, in this case you are sharer but not owner
        if (isset($ocmShareData["initiator"])) {
            $creatorParts = explode("@", $ocmShareData["initiator"]);
        } else {
            $creatorParts = explode("@", $share->getSharedBy());
        }

        if (count($granteeParts) != 2) {
            $creatorParts = ["unknown", "unknown"];
        }

        try {
            $filePath = $share->getNode()->getPath();
            // @Mahdi why is this hardcoded?
            // @Giuseppe this should be something that doesn't change when file is moved!
            $opaqueId = "fileid-" . $filePath;
        } catch (NotFoundException $e) {
            // @Mahdi why not just return status bad request or status not found?
            // @Michiel sometimes you want to translate share object even if file doesn't exist.
            $opaqueId = "unknown";
        }

        // TODO: @Mahdi update this comment to point at the Reva structure  mappings for this json.
        // produces JSON that maps to reva
        $payload = [
            // use OCM name, if null use efss share native name, if null fall back to "unknown"
            "name" => $ocmShareData["name"] ?? ($share->getName() ?? "unknown"),
            "token" => $token ?? "unknown",
            // TODO: @Mahdi what permissions is the correct one? share permissions has different value than the share->node permissions.
            // maybe use the ocmData for this one? needs testing for different scenarios to see which is the best/correct one.
            "permissions" => $share->getNode()->getPermissions() ?? 0,
            "id" => [
                // https://github.com/cs3org/go-cs3apis/blob/d297419/cs3/sharing/ocm/v1beta1/resources.pb.go#L423
                "opaque_id" => $shareId ?? "unknown",
            ],
            "resource_id" => [
                "opaque_id" => $opaqueId,
            ],
            // these three have been already handled and don't need "unknown" default values.
            "grantee" => [
                "id" => [
                    "opaque_id" => $granteeParts[0],
                    "idp" => $granteeParts[1],
                ],
            ],
            "owner" => [
                "id" => [
                    "opaque_id" => $ownerParts[0],
                    "idp" => $ownerParts[1],
                ],
            ],
            "creator" => [
                "id" => [
                    "opaque_id" => $creatorParts[0],
                    "idp" => $creatorParts[1],
                ],
            ],
            // NOTE: make sure seconds type is int, otherwise Reva gives:
            // error="json: cannot unmarshal string into Go struct field Timestamp.ctime.seconds of type uint64"
            "ctime" => [
                "seconds" => isset($ocmShareData["ctime"]) ? (int)$ocmShareData["ctime"] : ($share->getShareTime()->getTimestamp() ?? 0)
            ],
            "mtime" => [
                "seconds" => isset($ocmShareData["mtime"]) ? (int)$ocmShareData["ctime"] : ($share->getShareTime()->getTimestamp() ?? 0)
            ],
            "access_methods" => [
                "transfer" => [
                    "source_uri" => $ocmShareProtocols["transfer"]["source_uri"] ?? "unknown",
                    // TODO: @Mahdi this feels redundant, already included in top-level token and webdav shared_secret.
                    "shared_secret" => $ocmShareProtocols["transfer"]["shared_secret"] ?? "unknown",
                    // TODO: @Mahdi should the default value be an integer?
                    "size" => $ocmShareProtocols["transfer"]["size"] ?? "unknown",
                ],
                "webapp" => [
                    "uri_template" => $ocmShareProtocols["webapp"]["uri_template"] ?? "unknown",
                    "view_mode" => $ocmShareProtocols["webapp"]["view_mode"] ?? "unknown",
                ],
                "webdav" => [
                    // TODO: @Mahdi it is better to have sharedSecret and permissions in this part of code.
                    "uri" => $ocmShareProtocols["webdav"]["uri"] ?? "unknown",
                    // TODO: @Mahdi it is interesting this function accepts token as argument! is token different that the share secret?
                    // why do we have to pass token while the share object already has the information about token?
                    // $share->getToken();
                    "shared_secret" => $ocmShareProtocols["webdav"]["shared_secret"] ?? "unknown",
                    "permissions" => $ocmShareProtocols["webdav"]["permissions"] ?? "unknown",
                ],
            ]
        ];

        error_log("shareInfoToCs3Share " . var_export($payload, true));

        return $payload;
    }

    /**
     * add a received share.
     *
     * @NoCSRFRequired
     * @PublicPage
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     * @throws Exception
     */
    public function addReceivedShare($userId): JSONResponse
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $params = $this->request->getParams();
        error_log("addReceivedShare " . var_export($params, true));

        $name = $params["name"] ?? null;
        $ctime = (int)$params["ctime"]["seconds"] ?? null;
        $mtime = (int)$params["mtime"]["seconds"] ?? null;
        $remoteShareId = $params["remoteShareId"] ?? null;

        if (!isset($name)) {
            return new JSONResponse("name not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ctime)) {
            return new JSONResponse("ctime not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($mtime)) {
            return new JSONResponse("mtime not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($remoteShareId)) {
            return new JSONResponse("remoteShareId not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        if (isset($params["grantee"]["userId"])) {
            $granteeIdp = $params["grantee"]["userId"]["idp"] ?? null;
            $granteeOpaqueId = $params["grantee"]["userId"]["opaqueId"] ?? null;
        }

        if (isset($params["owner"])) {
            $ownerIdp = $params["owner"]["idp"] ?? null;
            $ownerOpaqueId = $params["owner"]["opaqueId"] ?? null;
        }

        if (isset($params["creator"])) {
            $creatorIdp = $params["creator"]["idp"] ?? null;
            $creatorOpaqueId = $params["creator"]["opaqueId"] ?? null;
        }

        if (!isset($granteeIdp)) {
            return new JSONResponse("grantee idp not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($granteeOpaqueId)) {
            return new JSONResponse("grantee opaqueId not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ownerIdp)) {
            return new JSONResponse("owner idp not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ownerOpaqueId)) {
            return new JSONResponse("owner opaqueId not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($creatorIdp)) {
            return new JSONResponse("creator idp not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($creatorOpaqueId)) {
            return new JSONResponse("creator opaqueId not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        if (!isset($params["protocols"])) {
            return new JSONResponse("protocols not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        $protocols = $params["protocols"];

        foreach ($protocols as $protocol) {
            if (isset($protocol["transferOptions"])) {
                $ocmProtocolTransfer = $protocol["transferOptions"];
            }
            if (isset($protocol["webappOptions"])) {
                $ocmProtocolWebapp = $protocol["webappOptions"];
            }
            if (isset($protocol["webdavOptions"])) {
                $ocmProtocolWebdav = $protocol["webdavOptions"];
            }
        }

        if (!isset($ocmProtocolWebdav)) {
            return new JSONResponse("webdavOptions not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        // handle bad cases and eliminate null variables.
        if (!isset($ocmProtocolWebdav["permissions"]["permissions"])) {
            return new JSONResponse("webdavOptions permissions not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ocmProtocolWebdav["sharedSecret"])) {
            return new JSONResponse("webdavOptions sharedSecret not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ocmProtocolWebdav["uri"])) {
            return new JSONResponse("webdavOptions uri not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        // convert permissions from array to integer.
        $integerPermissions = $this->getPermissionsCode($ocmProtocolWebdav["permissions"]["permissions"]);
        $ocmProtocolWebdav["permissions"] = $integerPermissions;

        $sharedSecret = $ocmProtocolWebdav["sharedSecret"];
        // URI example: https://nc1.docker/remote.php/dav/ocm/vaKE36Wf1lJWCvpDcRQUScraVP5quhzA
        $uri = $ocmProtocolWebdav["uri"];
        // remote extracted from URI: https://nc1.docker
        // below line splits uri by the "/" character, picks first 3 items aka ["https:", "", "nc.docker"]
        // and joins them again with "/" character in between.
        $remote = implode("/", array_slice(explode("/", $uri), 0, 3));

        if (!isset($remote)) {
            return new JSONResponse("Correct WebDAV URI not found in the request. remote is: $remote", Http::STATUS_BAD_REQUEST);
        }

        // TODO: @Mahdi write checks for webapp and transfer protocols missing properties and return STATUS_BAD_REQUEST.

        // remove trailing "/" from share name.
        $name = rtrim($name, "/");

        // prepare data for adding to the native efss table.
        $efssShareData = [
            // "https://nc1.docker"
            "remote" => $remote,
            // the id of the share in the oc_share table of the remote.
            "remote_id" => $remoteShareId,
            // "tDPRTrLI4hE3C5T"
            "share_token" => $sharedSecret,
            // password is always null. ScienceMesh doesn't have password protected shares.
            "password" => null,
            // "TestFolder"
            "name" => $name,
            // "einstein"
            "owner" => $ownerOpaqueId,
            // receiver "marie"
            "user" => $userId
        ];

        $efssShareId = $this->shareProvider->addReceivedOcmShareToEfssTable($efssShareData);

        // prepare data for adding to the ScienceMesh OCM table.
        // see: https://github.com/sciencemesh/nc-sciencemesh/issues/45
        $expiration = $params["expiration"] ?? null;
        if (isset($expiration)) {
            $expiration = (int)$expiration;
        }

        $ocmShareData = [
            "share_external_id" => $efssShareId,
            "name" => $name,
            "share_with" => $granteeOpaqueId . "@" . $granteeIdp,
            "owner" => $ownerOpaqueId . "@" . $ownerIdp,
            "initiator" => $creatorOpaqueId . "@" . $creatorIdp,
            "ctime" => $ctime,
            "mtime" => $mtime,
            "expiration" => $expiration,
            "remote_share_id" => $remoteShareId,
            "transfer" => $ocmProtocolTransfer ?? null,
            "webapp" => $ocmProtocolWebapp ?? null,
            "webdav" => $ocmProtocolWebdav,
        ];

        $this->shareProvider->addReceivedOcmShareToSciencemeshTable($ocmShareData);

        return new JSONResponse($efssShareId, Http::STATUS_CREATED);
    }

    /**
     * Create a new share in fn with the given access control list.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @return Http\DataResponse|JSONResponse
     *
     * @throws NotFoundException
     * @throws NotPermittedException
     * @throws Exception
     */
    public function addSentShare($userId)
    {
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $params = $this->request->getParams();
        error_log("addSentShare " . var_export($params, true));

        $name = $params["name"] ?? null;
        $token = $params["token"] ?? null;
        $ctime = (int)$params["ctime"]["seconds"] ?? null;
        $mtime = (int)$params["mtime"]["seconds"] ?? null;
        $resourceId = $params["resourceId"]["opaqueId"] ?? null;
        $payloadUserId = $params["userId"] ?? null;

        if (!isset($name)) {
            return new JSONResponse("name not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($token)) {
            return new JSONResponse("token not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ctime)) {
            return new JSONResponse("ctime not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($mtime)) {
            return new JSONResponse("mtime not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($resourceId)) {
            return new JSONResponse("resourceId->opaqueId not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($payloadUserId)) {
            return new JSONResponse("userId not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        // chained path conversions to verify this file exists in our server.
        // "fileid-/home/test" -> "/home/test" -> "/test"
        $revaPath = $this->revaPathFromOpaqueId($resourceId);
        $efssPath = $this->revaPathToEfssPath($revaPath);

        try {
            $node = $this->userFolder->get($efssPath);
        } catch (NotFoundException $e) {
            return new JSONResponse("share failed. resource path not found.", Http::STATUS_BAD_REQUEST);
        }

        if (isset($params["grantee"]["userId"])) {
            $granteeIdp = $params["grantee"]["userId"]["idp"] ?? null;
            $granteeOpaqueId = $params["grantee"]["userId"]["opaqueId"] ?? null;
        }

        if (isset($params["owner"])) {
            $ownerIdp = $params["owner"]["idp"] ?? null;
            $ownerOpaqueId = $params["owner"]["opaqueId"] ?? null;
        }

        if (isset($params["creator"])) {
            $creatorIdp = $params["creator"]["idp"] ?? null;
            $creatorOpaqueId = $params["creator"]["opaqueId"] ?? null;
        }

        if (!isset($granteeIdp)) {
            return new JSONResponse("grantee idp not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($granteeOpaqueId)) {
            return new JSONResponse("grantee opaqueId not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ownerIdp)) {
            return new JSONResponse("owner idp not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($ownerOpaqueId)) {
            return new JSONResponse("owner opaqueId not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($creatorIdp)) {
            return new JSONResponse("creator idp not found in the request.", Http::STATUS_BAD_REQUEST);
        }
        if (!isset($creatorOpaqueId)) {
            return new JSONResponse("creator opaqueId not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        // NOTE: @Mahdi this 3 variables should be exactly same as of now.
        // maybe it would be subject to change in future but for now it is good to check these
        // instead of blindly assuming they're same.
        if ($userId !== $payloadUserId || $userId !== $creatorOpaqueId || $payloadUserId !== $creatorOpaqueId) {
            return new JSONResponse(
                "creator->opaqueId, userId and userId from the payload are mismatched.",
                Http::STATUS_UNPROCESSABLE_ENTITY
            );
        }

        // don't allow ScienceMesh shares if source and target server are the same.
        // this means users with the same reva iop cannot share with each other via sciencemesh and
        // should use their native efss capabilities to do so.
        // see: https://github.com/sciencemesh/nc-sciencemesh/issues/57
        if ($ownerIdp === $granteeIdp) {
            $message = "Not allowed to create a ScienceMesh share for a user on the same server %s as sender %s.";
            $this->logger->debug(
                sprintf(
                    $message, $ownerIdp, $granteeIdp
                ),
                ["app" => "sciencemesh"]
            );
            return new JSONResponse(
                "Not allowed to create a ScienceMesh share for a user on the same server %s as sender %s.",
                Http::STATUS_UNPROCESSABLE_ENTITY
            );
        }

        if (!isset($params["accessMethods"])) {
            return new JSONResponse("accessMethods not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        $accessMethods = $params["accessMethods"];

        // TODO: @Mahdi these one has problems, check and debug.
        foreach ($accessMethods as $method) {
            if (isset($method["transferOptions"])) {
                $ocmProtocolTransfer = $method["transferOptions"];
            }
            if (isset($method["webappOptions"])) {
                $ocmProtocolWebapp = $method["webappOptions"];
            }
            if (isset($method["webdavOptions"])) {
                $ocmProtocolWebdav = $method["webdavOptions"];
            }
        }

        if (!isset($ocmProtocolWebdav)) {
            return new JSONResponse("webdavOptions not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        // handle bad cases and eliminate null variables.
        if (!isset($ocmProtocolWebdav["permissions"])) {
            return new JSONResponse("webdavOptions permissions not found in the request.", Http::STATUS_BAD_REQUEST);
        }

        // convert permissions from array to integer.
        $permissions = $this->getPermissionsCode($ocmProtocolWebdav["permissions"]);

        // prepare data for adding to the native efss table.
        $share = $this->shareManager->newShare();
        $share->setNode($node);

        $share->setShareType(ScienceMeshApp::SHARE_TYPE_SCIENCEMESH);
        $share->setSharedWith($granteeOpaqueId . "@" . $granteeIdp);
        $share->setShareOwner($ownerOpaqueId);
        $share->setSharedBy($creatorOpaqueId);
        $share->setPermissions($permissions);
        $share->setToken($token);
        $share->setShareTime(new DateTime("@$ctime"));

        // check if file is not already shared with the remote user
        $alreadyShared = $this->shareProvider->getSharedWith(
            $share->getSharedWith(),
            $share->getShareType(),
            $share->getNode(),
            1,
            0
        );

        if (!empty($alreadyShared)) {
            $message = "Sharing %s failed, because this item is already shared with %s";
            $message_t = $this->l->t(
                "Sharing %s failed, because this item is already shared with %s",
                [$share->getNode()->getName(), $share->getSharedWith()]
            );
            $this->logger->debug(
                sprintf(
                    $message, $share->getNode()->getName(), $share->getSharedWith()
                ),
                ["app" => "sciencemesh"]
            );
            return new JSONResponse($message_t, Http::STATUS_CONFLICT);
        }

        // ScienceMesh shares always have read permissions
        if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
            $message = 'ScienceMesh shares require read permissions';
            $message_t = $this->l->t('ScienceMesh shares require read permissions');
            $this->logger->debug($message, ['app' => 'ScienceMesh']);
            return new JSONResponse($message_t, Http::STATUS_UNPROCESSABLE_ENTITY);
        }

        $this->lock($share->getNode());

        // prepare share data for ocm
        $share = $this->shareProvider->createNativeEfssScienceMeshShare($share);
        $efssShareInternalId = $share->getId();

        // prepare data for adding to the ScienceMesh OCM table.
        // see: https://github.com/sciencemesh/nc-sciencemesh/issues/45

        $expiration = $params["expiration"] ?? null;
        if (isset($expiration)) {
            $expiration = (int)$expiration;
        }

        $ocmShareData = [
            "share_internal_id" => $efssShareInternalId,
            "name" => $name,
            "share_with" => $granteeOpaqueId . "@" . $granteeIdp,
            "owner" => $ownerOpaqueId . "@" . $ownerIdp,
            "initiator" => $creatorOpaqueId . "@" . $creatorIdp,
            "ctime" => $ctime,
            "mtime" => $mtime,
            "expiration" => $expiration,
            "transfer" => $ocmProtocolTransfer ?? null,
            "webapp" => $ocmProtocolWebapp ?? null,
            "webdav" => $ocmProtocolWebdav,
        ];

        $this->shareProvider->addSentOcmShareToSciencemeshTable($ocmShareData);

        return new DataResponse($efssShareInternalId, Http::STATUS_CREATED);
    }

    /**
     * getReceivedShare returns the information for a received share the user has access.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     */
    public function getReceivedShare($userId): JSONResponse
    {
        error_log("GetReceivedShare");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
        $name = $this->getNameByOpaqueId($opaqueId);

        try {
            $share = $this->shareProvider->getReceivedShareByToken($opaqueId);
            $response = $this->shareInfoToCs3Share($share, "received", $opaqueId);
            $response["state"] = 2;
            return new JSONResponse($response, Http::STATUS_OK);
        } catch (Exception $e) {
            return new JSONResponse(["error" => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * getSentShare gets the information for a share by the given ref.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     *
     * @throws InvalidPathException
     * @throws NotFoundException
     * @throws NotPermittedException
     */
    public function getSentShare($userId): JSONResponse
    {
        error_log("GetSentShare");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
        $name = $this->getNameByOpaqueId($opaqueId);
        $share = $this->shareProvider->getSentShareByName($userId, $name);

        if ($share) {
            $response = $this->shareInfoToCs3Share($share, "sent");
            return new JSONResponse($response, Http::STATUS_OK);
        }

        return new JSONResponse(["error" => "GetSentShare failed"], Http::STATUS_NOT_FOUND);
    }

    /**
     * getSentShareByToken gets the information for a share by the given token.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     *
     * @throws InvalidPathException
     * @throws NotFoundException
     * @throws NotPermittedException
     * @throws ShareNotFound
     * @throws IllegalIDChangeException
     */
    public function getSentShareByToken($userId): JSONResponse
    {
        error_log("GetSentShareByToken: user is -> $userId");

        // see: https://github.com/cs3org/reva/pull/4115#discussion_r1308371946
        if ($userId !== "nobody") {
            if ($this->userManager->userExists($userId)) {
                $this->init($userId);
            } else {
                return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
            }
        }

        $token = $this->request->getParam("Spec")["Token"];
        error_log("GetSentShareByToken: " . var_export($this->request->getParam("Spec"), true));

        // TODO: @Mahdi handle in try catch block and send back correct responses.
        $share = $this->shareProvider->getSentShareByToken($token);

        if ($share) {
            $response = $this->shareInfoToCs3Share($share, "sent", $token);
            return new JSONResponse($response, Http::STATUS_OK);
        }

        return new JSONResponse(["error" => "GetSentShare failed"], Http::STATUS_BAD_REQUEST);
    }

    /**
     * listReceivedShares returns the list of shares the user has access.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws InvalidPathException
     * @throws NotFoundException
     * @throws NotPermittedException
     */
    public function listReceivedShares($userId): JSONResponse
    {
        error_log("ListReceivedShares");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $responses = [];
        $shares = $this->shareProvider->getReceivedShares($userId);

        if ($shares) {
            foreach ($shares as $share) {
                $response = $this->shareInfoToCs3Share($share, "received");
                $responses[] = [
                    "share" => $response,
                    "state" => 2
                ];
            }
        }

        return new JSONResponse($responses, Http::STATUS_OK);
    }

    /**
     * listSentShares returns the shares created by the user. If md is provided is not nil,
     * it returns only shares attached to the given resource.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     *
     * @throws InvalidPathException
     * @throws NotFoundException
     * @throws NotPermittedException
     */
    public function listSentShares($userId): JSONResponse
    {
        error_log("ListSentShares");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $responses = [];
        $shares = $this->shareProvider->getSentShares($userId);

        if ($shares) {
            foreach ($shares as $share) {
                $responses[] = $this->shareInfoToCs3Share($share, "sent");
            }
        }
        return new JSONResponse($responses, Http::STATUS_OK);
    }

    # TODO: @Mahdi where is UpdateShare endpoint? not implemented?


    /**
     * Remove Share from share table
     *
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     *
     * @throws NotPermittedException
     */
    public function unshare($userId): JSONResponse
    {
        error_log("Unshare");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $opaqueId = $this->request->getParam("Spec")["Id"]["opaque_id"];
        $name = $this->getNameByOpaqueId($opaqueId);

        if ($this->shareProvider->deleteSentShareByName($userId, $name)) {
            return new JSONResponse("Deleted Sent Share", Http::STATUS_OK);
        } else {
            if ($this->shareProvider->deleteReceivedShareByOpaqueId($userId, $opaqueId)) {
                return new JSONResponse("Deleted Received Share", Http::STATUS_OK);
            } else {
                return new JSONResponse("Could not find share", Http::STATUS_BAD_REQUEST);
            }
        }
    }

    /**
     * updateReceivedShare updates the received share with share state.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     *
     * @throws NotPermittedException
     */
    public function updateReceivedShare($userId): JSONResponse
    {
        error_log("UpdateReceivedShare");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $resourceId = $this->request->getParam("received_share")["share"]["resource_id"];
        $permissions = $this->request->getParam("received_share")["share"]["permissions"];
        $permissionsCode = $this->getPermissionsCode($permissions);

        try {
            $share = $this->shareProvider->getReceivedShareByToken($resourceId);
            $share->setPermissions($permissionsCode);
            $shareUpdate = $this->shareProvider->UpdateReceivedShare($share);
            $response = $this->shareInfoToCs3Share($shareUpdate, "received", $resourceId);
            $response["state"] = 2;
            return new JSONResponse($response, Http::STATUS_OK);
        } catch (Exception $e) {
            return new JSONResponse(["error" => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @PublicPage
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     *
     * @throws InvalidPathException
     * @throws NotFoundException
     * @throws NotPermittedException
     */
    public function updateSentShare($userId): JSONResponse
    {
        error_log("UpdateSentShare");
        if ($this->userManager->userExists($userId)) {
            $this->init($userId);
        } else {
            return new JSONResponse("User not found", Http::STATUS_FORBIDDEN);
        }

        $opaqueId = $this->request->getParam("ref")["Spec"]["Id"]["opaque_id"];
        $permissions = $this->request->getParam("p")["permissions"];
        $permissionsCode = $this->getPermissionsCode($permissions);
        $name = $this->getNameByOpaqueId($opaqueId);
        if (!($share = $this->shareProvider->getSentShareByName($userId, $name))) {
            return new JSONResponse(["error" => "UpdateSentShare failed"], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $share->setPermissions($permissionsCode);
        $shareUpdated = $this->shareProvider->update($share);
        $response = $this->shareInfoToCs3Share($shareUpdated, "sent");
        return new JSONResponse($response, Http::STATUS_OK);
    }
}
