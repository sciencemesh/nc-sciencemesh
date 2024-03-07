<?php
/**
 * ScienceMesh Nextcloud plugin application.
 *
 * @copyright 2020 - 2024, ScienceMesh.
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.io>
 *
 * @license AGPL-3.0
 *
 *  This code is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License, version 3,
 *  as published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License, version 3,
 *  along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\ScienceMesh\Utils;

use Exception;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

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


class StaticMethods
{
	/** @var IL10N */
	private IL10N $l;

	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/**
	 * StaticMethods class.
	 *
	 * @param IL10N $l10n
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		IL10N           $l10n,
		LoggerInterface $logger
	)
	{
		$this->l = $l10n;
		$this->logger = $logger;
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
			return "";
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

	// For ListReceivedShares, GetReceivedShare and UpdateReceivedShare we need to include "state:2"
	// see:
	// https://github.com/cs3org/cs3apis/blob/cfd1ad29fdf00c79c2a321de7b1a60d0725fe4e8/cs3/sharing/ocm/v1beta1/resources.proto#L160
	/**
	 * @throws NotFoundException
	 */
	public function shareInfoToCs3Share(
		ScienceMeshShareProvider $shareProvider,
		IShare                   $share,
		string                   $direction,
		string                   $token = ""
	): array
	{
		$shareId = $share->getId();

		// TODO @Mahdi: use enums!
		if ($direction === "sent") {
			$ocmShareData = $shareProvider->getSentOcmShareFromSciencemeshTable($shareId);
			$ocmShareProtocols = $shareProvider->getSentOcmShareProtocolsFromSciencemeshTable($ocmShareData["id"]);
		} elseif ($direction === "received") {
			$ocmShareData = $shareProvider->getReceivedOcmShareFromSciencemeshTable($shareId);
			$ocmShareProtocols = $shareProvider->getReceivedOcmShareProtocolsFromSciencemeshTable($ocmShareData["id"]);
		}

		// use ocm payload stored in sciencemesh table. if it fails, use native efss share data.
		// in case of total failure use "unknown".

		// this one is obvious right?
		if (isset($ocmShareData["share_with"])) {
			$granteeParts = $this->splitUserAndHost($ocmShareData["share_with"]);
		} else {
			$granteeParts = $this->splitUserAndHost($share->getSharedWith());
		}

		if (count($granteeParts) != 2) {
			$granteeParts = ["unknown", "unknown"];
		}

		// the original share owner (who owns the path that is shared)
		if (isset($ocmShareData["owner"])) {
			$ownerParts = $this->splitUserAndHost($ocmShareData["owner"]);
		} else {
			$ownerParts = $this->splitUserAndHost($share->getShareOwner());
		}

		if (count($granteeParts) != 2) {
			$ownerParts = ["unknown", "unknown"];
		}

		// NOTE: @Mahdi initiator/creator/sharedBy etc., whatever other names it has! means the share sharer!
		// you can be owner and sharer, you can be someone who is re-sharing, in this case you are sharer but not owner
		if (isset($ocmShareData["initiator"])) {
			$creatorParts = $this->splitUserAndHost($ocmShareData["initiator"]);
		} else {
			$creatorParts = $this->splitUserAndHost($share->getSharedBy());
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

		// TODO: @Mahdi update this comment to point at the Reva structure mappings for this json.
		// produces JSON that maps to Reva.
		$payload = [
			// use OCM name, if null use efss share native name, if null fall back to "unknown"
			"name" => $ocmShareData["name"] ?? ($share->getNode()->getName() ?? "unknown"),
			"token" => $token,
			"id" => [
				// https://github.com/cs3org/go-cs3apis/blob/d297419/cs3/sharing/ocm/v1beta1/resources.pb.go#L423
				"opaque_id" => $shareId,
			],
			"resource_id" => [
				"opaque_id" => $opaqueId,
			],
			// these three have already been handled and don't need "unknown" default values.
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
				"seconds" => isset($ocmShareData["ctime"]) ?
					(int)$ocmShareData["ctime"] :
					($share->getShareTime()->getTimestamp() ?? 0),
			],
			"mtime" => [
				"seconds" => isset($ocmShareData["mtime"]) ?
					(int)$ocmShareData["ctime"] :
					($share->getShareTime()->getTimestamp() ?? 0),
			],
			"protocols" => [
				// TODO @Mahdi: if $ocmShareProtocols["webdav"]["uri"] == null, then we have a problem, raise an exception.
				"webdav" => [
					// TODO @Mahdi: $ocmShareProtocols is probably undefined!
					// why? because you need to use enums instead of $direction === "sent" or $direction === "received"
					"uri" => $ocmShareProtocols["webdav"]["uri"],
					// these are the share "OCS" permissions (integer)
					"permissions" => $share->getPermissions() ?? 0,
				],
			]
		];

		// if $ocmShareProtocols["transfer"]["source_uri"] != null, then include "transfer".
		if (isset($ocmShareProtocols["transfer"]["source_uri"])) {
			$payload["protocols"]["transfer"] = [
				"source_uri" => $ocmShareProtocols["transfer"]["source_uri"],
				"size" => $ocmShareProtocols["transfer"]["size"] ?? 0,
			];
		}

		// if $ocmShareProtocols["webapp"]["uri_template"] != null, then include "webapp"
		if (isset($ocmShareProtocols["webapp"]["uri_template"])) {
			$payload["protocols"]["webapp"] = [
				"uri_template" => $ocmShareProtocols["webapp"]["uri_template"],
				"view_mode" => $ocmShareProtocols["webapp"]["view_mode"],
			];
		}

		error_log("shareInfoToCs3Share " . var_export($payload, true));

		return $payload;
	}

	/**
	 * Checks whether the given target's domain part matches one of the server's
	 * trusted domain entries
	 *
	 * @param string $target target
	 * @return true if one match was found, false otherwise
	 */
	public function isInstanceDomain(string $target, IConfig $config): bool
	{
		if (strpos($target, "/") !== false) {
			// not a proper email-like format with domain name
			return false;
		}
		$parts = explode("@", $target);
		if (count($parts) === 1) {
			// no "@" sign
			return false;
		}
		$domainName = $parts[count($parts) - 1];
		$trustedDomains = $config->getSystemValue("trusted_domains", []);

		return in_array($domainName, $trustedDomains, true);
	}

	/**
	 * split user and remote from federated cloud id
	 *
	 * @param string $address federated share address
	 * @return array [user, remoteURL]
	 * @throws Exception
	 */
	public function splitUserRemote(string $address): array
	{
		if (strpos($address, "@") === false) {
			throw new Exception("Invalid Federated Cloud ID");
		}

		// Find the first character that is not allowed in usernames
		$id = str_replace("\\", "/", $address);
		$posSlash = strpos($id, "/");
		$posColon = strpos($id, ":");

		if ($posSlash === false && $posColon === false) {
			$invalidPos = strlen($id);
		} elseif ($posSlash === false) {
			$invalidPos = $posColon;
		} elseif ($posColon === false) {
			$invalidPos = $posSlash;
		} else {
			$invalidPos = min($posSlash, $posColon);
		}

		// Find the last @ before $invalidPos
		$pos = $lastAtPos = 0;
		while ($lastAtPos !== false && $lastAtPos <= $invalidPos) {
			$pos = $lastAtPos;
			$lastAtPos = strpos($id, "@", $pos + 1);
		}

		if ($pos !== false) {
			$user = substr($id, 0, $pos);
			$remote = substr($id, $pos + 1);
			$remote = $this->fixRemoteURL($remote);
			if (!empty($user) && !empty($remote)) {
				return [$user, $remote];
			}
		}

		throw new Exception("Invalid Federated Cloud ID");
	}

	/**
	 * Strips away a potential file names and trailing slashes:
	 * - http://localhost
	 * - http://localhost/
	 * - http://localhost/index.php
	 * - http://localhost/index.php/s/{shareToken}
	 *
	 * all return: http://localhost
	 *
	 * @param string $remote
	 * @return string
	 */
	protected function fixRemoteURL(string $remote): string
	{
		$remote = str_replace("\\", "/", $remote);
		if ($fileNamePosition = strpos($remote, "/index.php")) {
			$remote = substr($remote, 0, $fileNamePosition);
		}
		return rtrim($remote, "/");
	}

	public function splitUserAndHost(string $username, string $split_char = "@"): array
	{
		// it should split username@host into an array of 2 element
		// representing array[0] = username, array[1] = host
		// requirement:
		// handle usernames with multiple @ in them.
		// example: MahdiBaghbani@pondersource@sciencemesh.org
		// username: MahdiBaghbani@pondersource
		// host: sciencemesh.org
		$parts = explode($split_char, $username);
		$last = array_pop($parts);
		return array(implode($split_char, $parts), $last);
	}
}
