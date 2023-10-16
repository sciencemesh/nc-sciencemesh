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

namespace OCA\ScienceMesh\Utils;

use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\IShare;

/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

const RESTRICT_TO_SCIENCEMESH_FOLDER = false;
const EFSS_PREFIX = (RESTRICT_TO_SCIENCEMESH_FOLDER ? "sciencemesh/" : "");

// See https://github.com/pondersource/sciencemesh-php/issues/96#issuecomment-1298656896
const REVA_PREFIX = "/home/";


class Utils
{
    /** @var IL10N */
    private IL10N $l;

    /** @var ILogger */
    private ILogger $logger;

    /** @var ScienceMeshShareProvider */
    private ScienceMeshShareProvider $shareProvider;

    /**
     * Utils class.
     *
     * @param IL10N $l10n
     * @param ILogger $logger
     * @param ScienceMeshShareProvider $shareProvider
     */
    public function __construct(
        IL10N                    $l10n,
        ILogger                  $logger,
        ScienceMeshShareProvider $shareProvider
    )
    {
        $this->l = $l10n;
        $this->logger = $logger;
        $this->shareProvider = $shareProvider;
    }

    public function removePrefix($string, $prefix)
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

    public function revaPathToEfssPath($revaPath): string
    {
        if ("$revaPath/" == REVA_PREFIX) {
            error_log("revaPathToEfssPath: Interpreting special case $revaPath as ''");
            return '';
        }
        $ret = EFSS_PREFIX . $this->removePrefix($revaPath, REVA_PREFIX);
        error_log("revaPathToEfssPath: Interpreting $revaPath as $ret");
        return $ret;
    }

    public function revaPathFromOpaqueId($opaqueId)
    {
        return $this->removePrefix($opaqueId, "fileid-");
    }

    public function efssPathToRevaPath($efssPath): string
    {
        $ret = REVA_PREFIX . $this->removePrefix($efssPath, EFSS_PREFIX);
        error_log("efssPathToRevaPath: Interpreting $efssPath as $ret");
        return $ret;
    }

    public function efssFullPathToRelativePath($efssFullPath, string $userFolderPath)
    {
        $ret = $this->removePrefix($efssFullPath, $userFolderPath);
        error_log("efssFullPathToRelativePath: Interpreting $efssFullPath as $ret");
        return $ret;
    }

    public function getChecksum(Node $node, $checksumType = 4): string
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
     * @throws NotPermittedException
     */
    public function checkRevadAuth(IRequest $request, string $revaSharedSecret)
    {
        error_log("checkRevadAuth");
        $authHeader = $request->getHeader("X-Reva-Secret");

        if ($authHeader != $revaSharedSecret) {
            throw new NotPermittedException('Please set an http request header "X-Reva-Secret: <your_shared_secret>"!');
        }
    }

    /**
     * @param Node $node
     * @return void
     *
     * @throws LockedException
     */
    public function lock(Node $node)
    {
        $node->lock(ILockingProvider::LOCK_SHARED);
    }

    public function getPermissionsCode(array $permissions): int
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

    /**
     * @throws InvalidPathException
     * @throws NotFoundException
     */
    public function nodeToCS3ResourceInfo(Node $node, string $userFolderPath, string $userId, string $Idp): array
    {
        $isDirectory = ($node->getType() === FileInfo::TYPE_FOLDER);
        $efssPath = substr($node->getPath(), strlen($userFolderPath) + 1);
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
                "opaque_id" => $userId,
                "idp" => $Idp,
            ]
        ];

        error_log("nodeToCS3ResourceInfo " . var_export($payload, true));

        return $payload;
    }

    // For ListReceivedShares, GetReceivedShare and UpdateReceivedShare we need to include "state:2"
    // see:
    // https://github.com/cs3org/cs3apis/blob/cfd1ad29fdf00c79c2a321de7b1a60d0725fe4e8/cs3/sharing/ocm/v1beta1/resources.proto#L160
    /**
     * @throws NotFoundException
     * @throws InvalidPathException
     */
    public function shareInfoToCs3Share(IShare $share, string $direction, string $token = ""): array
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

    public function formatUser(IUser $user, string $idp): array
    {
        return [
            "id" => [
                "idp" => $idp,
                "opaque_id" => $user->getUID(),
            ],
            "display_name" => $user->getDisplayName(),
            "username" => $user->getUID(),
            "email" => $user->getEmailAddress(),
            "type" => 1,
        ];
    }
}
