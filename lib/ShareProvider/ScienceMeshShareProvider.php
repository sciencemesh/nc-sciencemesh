<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sergej Pupykin <pupykin.s@gmail.com>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\ScienceMesh\ShareProvider;

use OC\GlobalScale\GlobalScaleConfig;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Share;
use OCP\Constants;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCA\ScienceMesh\GlobalConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;
use OCA\ScienceMesh\RevaHttpClient;

/**
 * Class ScienceMeshShareProvider
 *
 * @package OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider
 */
class ScienceMeshShareProvider implements IShareProvider {
	/** @var IDBConnection */
	private $dbConnection;

	/** @var TokenHandler */
//	private $tokenHandler;

	/** @var IL10N */
	private $l;

	/** @var ILogger */
	private $logger;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IConfig */
	private $config;

	/** @var string */
	private $externalShareTable = 'share_external';

	/** @var IUserManager */
	private $userManager;

	/** @var GlobalConfig\IGlobalScaleConfig*/
	private $gsConfig;

	/** @var array list of supported share types */
	private $supportedShareType = [\OCP\Share::SHARE_TYPE_REMOTE];

	/**
	 * DefaultShareProvider constructor.
	 *
	 * @param IDBConnection $connection
	 * @param IL10N $l10n
	 * @param ILogger $logger
	 * @param IRootFolder $rootFolder
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 */
	public function __construct(
			IDBConnection $connection,
			IL10N $l10n,
			ILogger $logger,
			IRootFolder $rootFolder,
			IConfig $config,
			IUserManager $userManager
	) {
		$this->dbConnection = $connection;
		$this->l = $l10n;
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->gsConfig = new GlobalConfig\GlobalScaleConfig($config);
		$this->revaHttpClient = new RevaHttpClient($config);
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'sciencemesh';
	}

	/**
	 * Check if a given share type is supported by this provider.
	 *
	 * @param int $shareType
	 *
	 * @return boolean
	 */
	public function isShareTypeSupported($shareType) {
		return in_array($shareType, $this->supportedShareType);
	}

	/**
	 * Share a path
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 * @throws ShareNotFound
	 * @throws \Exception
	 */
	public function createInternal(IShare $share) {
		error_log("SMSP: createInternal");
		$shareWith = $share->getSharedWith();
		$itemSource = $share->getNodeId();
		$itemType = $share->getNodeType();
		$permissions = $share->getPermissions();
		$sharedBy = $share->getSharedBy();
		$shareType = $share->getShareType();

		error_log("shareWith $shareWith");

		error_log("checking if already shared " . $share->getNode()->getName());
		/*
		 * Check if file is not already shared with the remote user
		 */
		$alreadyShared = $this->getSharedWith($shareWith,  \OCP\Share::SHARE_TYPE_REMOTE, $share->getNode(), 1, 0);
		if (!empty($alreadyShared)) {
			$message = 'Sharing %1$s failed, because this item is already shared with %2$s';
			$message_t = $this->l->t('Sharing %1$s failed, because this item is already shared with user %2$s', [$share->getNode()->getName(), $shareWith]);
			$this->logger->debug(sprintf($message, $share->getNode()->getName(), $shareWith), ['app' => 'ScienceMesh']);
			throw new \Exception($message_t);
		}


		// FIXME: don't allow ScienceMesh shares if source and target server are the same
		// ScienceMesh shares always have read permissions
		if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
			$message = 'ScienceMesh shares require read permissions';
			$message_t = $this->l->t('ScienceMesh shares require read permissions');
			$this->logger->debug($message, ['app' => 'ScienceMesh']);
			throw new \Exception($message_t);
		}

		$share->setSharedWith($shareWith);
		/*
				try {
					$remoteShare = $this->getShareFromExternalShareTable($share);
				} catch (ShareNotFound $e) {
					$remoteShare = null;
				}

				if ($remoteShare) {
					try {
						$ownerCloudId = $this->cloudIdManager->getCloudId($remoteShare['owner'], $remoteShare['remote']);
						$shareId = $this->addShareToDB($itemSource, $itemType, $shareWith, $sharedBy, $ownerCloudId->getId(), $permissions, 'tmp_token_' . time(), $shareType);
						$share->setId($shareId);
						list($token, $remoteId) = $this->askOwnerToReShare($shareWith, $share, $shareId);
						// remote share was create successfully if we get a valid token as return
						$send = is_string($token) && $token !== '';
					} catch (\Exception $e) {
						// fall back to old re-share behavior if the remote server
						// doesn't support flat re-shares (was introduced with Nextcloud 9.1)
						$this->removeShareFromTable($share);
						$shareId = $this->createFederatedShare($share);
					}
					if ($send) {
						$this->updateSuccessfulReshare($shareId, $token);
						$this->storeRemoteId($shareId, $remoteId);
					} else {
						$this->removeShareFromTable($share);
						$message_t = $this->l->t('File is already shared with %s', [$shareWith]);
						throw new \Exception($message_t);
					}
				} else {
					$shareId = $this->createFederatedShare($share);
				}
		*/
		$shareId = $this->createScienceMeshShare($share);

		$data = $this->getRawShare($shareId);
		return $this->createShareObject($data);
	}
	/**
	 * Share a path
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 * @throws ShareNotFound
	 * @throws \Exception
	 */
	public function create(IShare $share) {
		error_log("SSP-createShare calling RHC-createShare");
		$node = $share->getNode();
		$shareWith = $share->getSharedWith();
		// $share->setPermissions($permissions);
		$pathParts = explode("/", $node->getPath());
		$sender = $pathParts[1];
		$sourceOffset = 3;
		$targetOffset = 3;
		$prefix = "/";
		$suffix = ($node->getType() == "dir" ? "/" : "");

		// "home" is reva's default work space name, prepending that in the source path:
		$sourcePath = $prefix . "home/" . implode("/", array_slice($pathParts, $sourceOffset)) . $suffix;
		$targetPath = $prefix . implode("/", array_slice($pathParts, $targetOffset)) . $suffix;
		$shareWithParts = explode("@", $shareWith);
		error_log("SAH-createShare calling RHC-createShare");
		$response = $this->revaHttpClient->createShare($sender, [
			'sourcePath' => $sourcePath,
			'targetPath' => $targetPath,
			'type' => $node->getType(),
			'recipientUsername' => $shareWithParts[0],
			'recipientHost' => $shareWithParts[1]
		]);
		if (!isset($response) || !isset($response->share) || !isset($response->share->owner) || !isset($response->share->owner->idp)) {
			throw new \Exception("Unexpected response from reva");
		}
		error_log("Back in SSP-createShare after RHC-createShare");
		error_log(var_export($response->share->id->opaqueId, true));
		error_log(var_export($response->share->owner->idp, true));
		// error_log(var_export($response, true));
		$share->setId("will-set-this-later");
		error_log("setId done");
		$share->setProviderId($response->share->owner->idp);
		error_log("setProviderId done");
		$share->setShareTime(new \DateTime());
		error_log("SSP-createShare done");
		return $share;
	}

	/**
	 * create sciencemesh share and inform the recipient
	 *
	 * @param IShare $share
	 * @return int
	 * @throws ShareNotFound
	 * @throws \Exception
	 */
	protected function createScienceMeshShare(IShare $share) {
		error_log("SMSP: Creating ScienceMesh share!");

		$token = $share->getToken(); // $this->tokenHandler->generateToken();
		$shareId = $this->addSentShareToDB(
			$share->getNodeId(),
			$share->getNodeType(),
			$share->getSharedWith(),
			$share->getSharedBy(),
			$share->getShareOwner(),
			$share->getPermissions(),
			$token,
			$share->getShareType()
		);
		return $shareId;

		// $failure = false;

		// try {
		// 		error_log("sending Share to reva");
		// 		$node = $share->getNode();
		// 		// $share->setSharedWith($shareWith);
		// 		// $share->setPermissions($permissions);
		// 		$pathParts = explode("/", $node->getPath());
		// 		$sender = $pathParts[1];
		// 		$sourceOffset = 3;
		// 		$targetOffset = 3;
		// 		$prefix = "/";
		// 		$suffix = ($node->getType() == "dir" ? "/" : "");
		
		// 		// "home" is reva's default work space name, prepending that in the source path:
		// 		$sourcePath = $prefix . "home/" . implode("/", array_slice($pathParts, $sourceOffset)) . $suffix;
		// 		$targetPath = $prefix . implode("/", array_slice($pathParts, $targetOffset)) . $suffix;
		// 		$shareWith = $share->getSharedWith();
		// 		$shareWithParts = explode("@", $shareWith);
		// 		$details = [
		// 			'sourcePath' => $sourcePath,
		// 			'targetPath' => $targetPath,
		// 			'type' => $node->getType(),
		// 			'recipientUsername' => $shareWithParts[0],
		// 			'recipientHost' => $shareWithParts[1]
		// 		];
		// 		foreach ($details as $k => $v) {
		// 			error_log("share detail '$k': '$v'");
		// 		}
		// 		$result = $this->revaHttpClient->createShare($sender, $details);
		// 		if ($result == false) {
		// 			error_log("Error response from Reva while creating share");
		// 			$failure = $this->l->t('Sharing %1$s failed, error response from Reva while creating share.',
		// 				[$share->getNode()->getName(), $share->getSharedWith()]);
		// 		} else {
		// 			error_log("OK response from Reva while creating share");

		// 		}
		// 	} catch (\Exception $e) {
		// 	error_log("exception: " . var_export($e, true));
		// 	$this->logger->logException($e, [
		// 		'message' => 'Failed to notify remote server of federated share, removing share.',
		// 		'level' => ILogger::ERROR,
		// 		'app' => 'federatedfilesharing',
		// 	]);
		// 	$failure = $this->l->t('Sharing %1$s failed, could not find %2$s, maybe the server is currently unreachable or uses a self-signed certificate.',
		// 	[$share->getNode()->getName(), $share->getSharedWith()]);
		// }

		// if ($failure) {
		// 	$this->removeShareFromTableById($shareId);
		// 	throw new \Exception($failure);
		// }

		// error_log("done");
		// return $shareId;
	}

	/**
	 * @param string $shareWith
	 * @param IShare $share
	 * @param string $shareId internal share Id
	 * @return array
	 * @throws \Exception
	 */
	protected function askOwnerToReShare($shareWith, IShare $share, $shareId) {
		$remoteShare = $this->getShareFromExternalShareTable($share);
		$token = $remoteShare['share_token'];
		$remoteId = $remoteShare['remote_id'];
		$remote = $remoteShare['remote'];

		[$token, $remoteId] = $this->notifications->requestReShare(
			$token,
			$remoteId,
			$shareId,
			$remote,
			$shareWith,
			$share->getPermissions(),
			$share->getNode()->getName()
		);

		return [$token, $remoteId];
	}

	/**
	 * get sciencemesh share from the share_external table but exclude mounted link shares
	 *
	 * @param IShare $share
	 * @return array
	 * @throws ShareNotFound
	 */
	protected function getShareFromExternalShareTable(IShare $share) {
		error_log("SMSP: getShareFromExternalShareTable!");
		$query = $this->dbConnection->getQueryBuilder();
		$query->select('*')->from($this->externalShareTable)
			->where($query->expr()->eq('user', $query->createNamedParameter($share->getShareOwner())))
			->andWhere($query->expr()->eq('mountpoint', $query->createNamedParameter($share->getTarget())));
		$qResult = $query->execute();
		$result = $qResult->fetchAll();
		$qResult->closeCursor();

		if (isset($result[0]) && (int)$result[0]['remote_id'] > 0) {
			return $result[0];
		}

		throw new ShareNotFound('share not found in share_external table');
	}

	/**
	 * add share to the database and return the ID
	 *
	 * @param int $itemSource
	 * @param string $itemType
	 * @param string $shareWith
	 * @param string $sharedBy
	 * @param string $uidOwner
	 * @param int $permissions
	 * @param string $token
	 * @param int $shareType
	 * @return int
	 */
	private function addSentShareToDB($itemSource, $itemType, $shareWith, $sharedBy, $uidOwner, $permissions, $token, $shareType) {
		error_log("SMSP: addSentShareToDB!");
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter($shareType))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_owner', $qb->createNamedParameter($uidOwner))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('token', $qb->createNamedParameter($token))
			->setValue('stime', $qb->createNamedParameter(time()));

		/*
		 * Added to fix https://github.com/owncloud/core/issues/22215
		 * Can be removed once we get rid of ajax/share.php
		 */
		$qb->setValue('file_target', $qb->createNamedParameter(''));

		$qb->execute();
		$id = $qb->getLastInsertId();

		return (int)$id;
	}

	/**
	 * add share to the database and return the ID
	 *
	 * @param int $itemSource
	 * @param string $itemType
	 * @param string $shareWith
	 * @param string $sharedBy
	 * @param string $uidOwner
	 * @param int $permissions
	 * @param string $token
	 * @param int $shareType
	 * @return int
	 */
	public function addReceivedShareToDB($shareData) {
		error_log("SMSP: addReceivedShareToDB");
		$share_type =  \OCP\Share::SHARE_TYPE_REMOTE;
		$mountpoint = "{{TemporaryMountPointName#" . $shareData["name"] . "}}";
		$mountpoint_hash = md5($mountpoint);
		$qbt = $this->dbConnection->getQueryBuilder();
		$qbt->select('*')
			->from('share_external')
			->where($qbt->expr()->eq('user', $qbt->createNamedParameter($shareData["user"])))
			->andWhere($qbt->expr()->eq('mountpoint_hash', $qbt->createNamedParameter($mountpoint_hash)));
		$cursor = $qbt->execute();
		if ($data = $cursor->fetch()) {
			return $data['id'];
		};
		// 0 => pending, 1 => accepted, 2 => rejected
        $accepted = 0; //pending
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert('share_external')
			// ->setValue('share_type', $qb->createNamedParameter($share_type))
			->setValue('remote', $qb->createNamedParameter($shareData["remote"]))
			->setValue('remote_id', $qb->createNamedParameter($shareData["remote_id"]))
			->setValue('share_token', $qb->createNamedParameter($shareData["share_token"]))
			->setValue('password', $qb->createNamedParameter($shareData["password"]))
			->setValue('name', $qb->createNamedParameter($shareData["name"]))
			->setValue('owner', $qb->createNamedParameter($shareData["owner"]))
			->setValue('user', $qb->createNamedParameter($shareData["user"]))
			->setValue('mountpoint', $qb->createNamedParameter($mountpoint))
			->setValue('mountpoint_hash', $qb->createNamedParameter($mountpoint_hash))
			->setValue('accepted', $qb->createNamedParameter($accepted));
		$qb->execute();
		$id = $qb->getLastInsertId();

		return (int)$id;
	}


	/**
	 * Update a sent share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 */
	public function update(IShare $share) {
		error_log("SMSP: update!");
		/*
		 * We allow updating the permissions of sciencemesh shares
		 */
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				->set('permissions', $qb->createNamedParameter($share->getPermissions()))
				->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
				->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
				->execute();

		// send the updated permission to the owner/initiator, if they are not the same
		if ($share->getShareOwner() !== $share->getSharedBy()) {
			$this->sendPermissionUpdate($share);
		}

		return $share;
	}
	/**
	 * Update a received share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 */
	public function updateReceivedShare(IShare $share) {
		error_log("SMSP: updateReceivedShare!");

		/*
		 * We allow updating the permissions of sciencemesh shares
		 */
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share_external')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
				->set('owner', $qb->createNamedParameter($share->getShareOwner()))
				->execute();
		return $share;
	}
	/**
	 * send the updated permission to the owner/initiator, if they are not the same
	 *
	 * @param IShare $share
	 * @throws ShareNotFound
	 * @throws \OC\HintException
	 */
	protected function sendPermissionUpdate(IShare $share) {
		error_log("SMSP: sendPermissionUpdate!");

		$remoteId = $this->getRemoteId($share);
		// if the local user is the owner we send the permission change to the initiator
		if ($this->userManager->userExists($share->getShareOwner())) {
			[, $remote] = $this->addressHandler->splitUserRemote($share->getSharedBy());
		} else { // ... if not we send the permission change to the owner
			[, $remote] = $this->addressHandler->splitUserRemote($share->getShareOwner());
		}
		$this->notifications->sendPermissionChange($remote, $remoteId, $share->getToken(), $share->getPermissions());
	}


	/**
	 * update successful reShare with the correct token
	 *
	 * @param int $shareId
	 * @param string $token
	 */
	protected function updateSuccessfulReShare($shareId, $token) {
		error_log("SMSP: updateSuccessfulReShare!");
		$query = $this->dbConnection->getQueryBuilder();
		$query->update('share')
			->where($query->expr()->eq('id', $query->createNamedParameter($shareId)))
			->set('token', $query->createNamedParameter($token))
			->execute();
	}

	/**
	 * store remote ID in sciencemesh reShare table
	 *
	 * @param $shareId
	 * @param $remoteId
	 */
	public function storeRemoteId(int $shareId, string $remoteId): void {
		error_log("SMSP: storeRemoteId!");
		$query = $this->dbConnection->getQueryBuilder();
		$query->insert('federated_reshares')
			->values(
				[
					'share_id' => $query->createNamedParameter($shareId),
					'remote_id' => $query->createNamedParameter($remoteId),
				]
			);
		$query->execute();
	}

	/**
	 * get share ID on remote server for sciencemesh re-shares
	 *
	 * @param IShare $share
	 * @return string
	 * @throws ShareNotFound
	 */
	public function getRemoteId(IShare $share): string {
		error_log("SMSP: getRemoteId!");

		$query = $this->dbConnection->getQueryBuilder();
		$query->select('remote_id')->from('federated_reshares')
			->where($query->expr()->eq('share_id', $query->createNamedParameter((int)$share->getId())));
		$result = $query->execute();
		$data = $result->fetch();
		$result->closeCursor();

		if (!is_array($data) || !isset($data['remote_id'])) {
			throw new ShareNotFound();
		}

		return (string)$data['remote_id'];
	}

	/**
	 * @inheritdoc
	 */
	public function move(IShare $share, $recipient) {
		error_log("SMSP: move!");

		/*
		 * This function does nothing yet as it is just for outgoing
		 * federated shares.
		 */
		return $share;
	}

	/**
	 * Get all children of this share
	 *
	 * @param IShare $parent
	 * @return IShare[]
	 */
	public function getChildren(IShare $parent) {
		error_log("SMSP: getChildren!");

		$children = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($parent->getId())))
			->andWhere($qb->expr()->in('share_type', $qb->createNamedParameter($this->supportedShareType, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$children[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $children;
	}

	/**
	 * Delete a share (owner unShares the file)
	 *
	 * @param IShare $share
	 * @throws ShareNotFound
	 * @throws \OC\HintException
	 */
	public function delete(IShare $share) {
				error_log("SMSP: delete!");

		// throw new Exception("Whoah");
		/*
				list(, $remote) = $this->addressHandler->splitUserRemote($share->getSharedWith());

				// if the local user is the owner we can send the unShare request directly...
				if ($this->userManager->userExists($share->getShareOwner())) {
					$this->notifications->sendRemoteUnShare($remote, $share->getId(), $share->getToken());
					$this->revokeShare($share, true);
				} else { // ... if not we need to correct ID for the unShare request
					$remoteId = $this->getRemoteId($share);
					$this->notifications->sendRemoteUnShare($remote, $remoteId, $share->getToken());
					$this->revokeShare($share, false);
				}
		*/
		// only remove the share when all messages are send to not lose information
		// about the share to early
		$this->removeShareFromTable($share);
	}

	/**
	 * in case of a re-share we need to send the other use (initiator or owner)
	 * a message that the file was unshared
	 *
	 * @param IShare $share
	 * @param bool $isOwner the user can either be the owner or the user who re-sahred it
	 * @throws ShareNotFound
	 * @throws \OC\HintException
	 */
	protected function revokeShare($share, $isOwner) {
		error_log("SMSP: revokeShare!");

		if ($this->userManager->userExists($share->getShareOwner()) && $this->userManager->userExists($share->getSharedBy())) {
			// If both the owner and the initiator of the share are local users we don't have to notify anybody else
			return;
		}


        //****
		// also send a unShare request to the initiator, if this is a different user than the owner
		if ($share->getShareOwner() !== $share->getSharedBy()) {
			if ($isOwner) {
				[, $remote] = $this->addressHandler->splitUserRemote($share->getSharedBy());
			} else {
				[, $remote] = $this->addressHandler->splitUserRemote($share->getShareOwner());
			}
			$remoteId = $this->getRemoteId($share);
			$this->notifications->sendRevokeShare($remote, $remoteId, $share->getToken());
		}
	}

	/**
	 * remove share from table
	 *
	 * @param IShare $share
	 */
	public function removeShareFromTable(IShare $share) {
		error_log("SMSP: removeShareFromTable!");

		$this->removeShareFromTableById($share->getId());
	}

	/**
	 * remove share from table
	 *
	 * @param string $shareId
	 */
	private function removeShareFromTableById($shareId) {
		error_log("SMSP: removeShareFromTableById!");

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('federated_reshares')
			->where($qb->expr()->eq('share_id', $qb->createNamedParameter($shareId)));
		$qb->execute();

		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($shareId)))
			->andWhere($qb->expr()->in('share_type', $qb->createNamedParameter($this->supportedShareType, IQueryBuilder::PARAM_INT_ARRAY)));

		$qb->execute();
	}

	/**
	 * @inheritdoc
	 */
	public function deleteFromSelf(IShare $share, $recipient) {
		error_log("SMSP: deleteFromSelf!");

		// nothing to do here. Technically deleteFromSelf in the context of federated
		// shares is a umount of an external storage. This is handled here
		// apps/files_sharing/lib/external/manager.php
		// TODO move this code over to this app
	}

	public function restore(IShare $share, string $recipient): IShare {
		error_log("SMSP: restore!");

		throw new GenericShareException('not implemented');
	}


	public function getSharesInFolder($userId, Folder $node, $reshares) {
		error_log("SMSP: getSharesInFolder!");

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share', 's')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->andWhere(
				$qb->expr()->eq('share_type', $qb->createNamedParameter( \OCP\Share::SHARE_TYPE_REMOTE))
			);

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		$qb->innerJoin('s', 'filecache' ,'f', $qb->expr()->eq('s.file_source', 'f.fileid'));
		$qb->andWhere($qb->expr()->eq('f.parent', $qb->createNamedParameter($node->getId())));

		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[$data['fileid']][] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		error_log("SMSP: getSharesBy!");

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter($shareType)));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			//Special case for old shares created via the web UI
			$or1 = $qb->expr()->andX(
				$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
				$qb->expr()->isNull('uid_initiator')
			);

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)),
					$or1
				)
			);
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}

		$qb->setFirstResult($offset);
		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getShareById($id, $recipientId = null) {
		error_log("SMSP: getShareById!");

		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->in('share_type', $qb->createNamedParameter($this->supportedShareType, IQueryBuilder::PARAM_INT_ARRAY)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound('Can not find share with ID: ' . $id);
		}

		try {
			$share = $this->createShareObject($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}

		return $share;
	}

	/**
	 * Get shares for a given path
	 *
	 * @param \OCP\Files\Node $path
	 * @return IShare[]
	 */
	public function getSharesByPath(Node $path) {
		error_log("SMSP: getSharesByPath!");

		$qb = $this->dbConnection->getQueryBuilder();

		// get federated user shares
		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere($qb->expr()->in('share_type', $qb->createNamedParameter($this->supportedShareType, IQueryBuilder::PARAM_INT_ARRAY)))
			->execute();

		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		error_log("SMSP: getSharedWith!");

		/** @var IShare[] $shares */
		$shares = [];

		//Get shares directly with this user
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		// Order by id
		$qb->orderBy('id');

		// Set limit and offset
		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}
		$qb->setFirstResult($offset);

		$qb->where($qb->expr()->in('share_type', $qb->createNamedParameter($this->supportedShareType, IQueryBuilder::PARAM_INT_ARRAY)));
		$qb->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)));

		// Filter by node if provided
		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		$cursor = $qb->execute();

		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();
    $nodeId = $node->getId();
		return $shares;
	}

	/**
	 * Get a share by token
	 *
	 * @param string $token
	 * @return IShare
	 * @throws ShareNotFound
	 */
	public function getShareByToken($token) {
		error_log("SMSP: getShareByToken!");

		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->in('share_type', $qb->createNamedParameter($this->supportedShareType, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->execute();

		$data = $cursor->fetch();

		if ($data === false) {
			throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
		}

		try {
			$share = $this->createShareObject($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
		}

		return $share;
	}
	/**
	 * Get a share by token
	 *
	 * @param string $token
	 * @return IShare
	 * @throws ShareNotFound
	 */
	public function getReceivedShareByToken($token) {
		error_log("SMSP: getReceivedShareByToken!");

		$qb = $this->dbConnection->getQueryBuilder();
		$cursor = $qb->select('*')
			->from('share_external')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(14)))
			->andWhere($qb->expr()->eq('share_token', $qb->createNamedParameter($token)))
			->execute();
		$data = $cursor->fetch();
		if ($data === false) {
			throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
		}
		try {
			$share = $this->createExternalShareObject($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
		}

		return $share;
	}

	/**
	 * get database row of a give share
	 *
	 * @param $id
	 * @return array
	 * @throws ShareNotFound
	 */
	private function getRawShare($id) {
		error_log("SMSP: getRawShare!");


		// Now fetch the inserted share and create a complete share object
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound;
		}

		return $data;
	}

	/**
	 * Create a share object from an database row
	 *
	 * @param array $data
	 * @return IShare
	 * @throws InvalidShare
	 * @throws ShareNotFound
	 */
	private function createShareObject($data) {
		error_log("SMSP: createShareObject!");

		$share = new Share($this->rootFolder, $this->userManager);
		$share->setId((int)$data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setMailSend((bool)$data['mail_send'])
			->setToken($data['token']);

		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);
		$share->setSharedWith($data['share_with']);

		if ($data['uid_initiator'] !== null) {
			$share->setShareOwner($data['uid_owner']);
			$share->setSharedBy($data['uid_initiator']);
		} else {
			//OLD SHARE
			$share->setSharedBy($data['uid_owner']);
			$path = $this->getNode($share->getSharedBy(), (int)$data['file_source']);

			$owner = $path->getOwner();
			$share->setShareOwner($owner->getUID());
		}

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);

		$share->setProviderId($this->identifier());

		return $share;
	}
	/**
	 * Create a share object from a database row from external shares
	 *
	 * @param array $data
	 * @return IShare
	 * @throws InvalidShare
	 * @throws ShareNotFound
	 */
	private function createExternalShareObject($data) {
		error_log("SMSP: createExternalShareObject!");

		$share = new Share($this->rootFolder, $this->userManager);
		$share->setId((int)$data['id'])
			->setShareType((int)$data['share_type'])
			->setShareOwner($data['owner'])
			->setSharedBy($data['owner'])
			->setToken($data['share_token'])
			->setSharedWith($data['user']);
		$share->setProviderId($this->identifier());

		return $share;
	}
	/**
	 * Get the node with file $id for $user
	 *
	 * @param string $userId
	 * @param int $id
	 * @return Node
     * @throws InvalidShare
	 */
	private function getNode($userId, $id) {
		error_log("SMSP: getNode!");

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (NotFoundException $e) {
			throw new InvalidShare();
		}

		$nodes = $userFolder->getById($id);

		if (empty($nodes)) {
			throw new InvalidShare();
		}

		return $nodes[0];
	}

	/**
	 * A user is deleted from the system
	 * So clean up the relevant shares.
	 *
	 * @param string $uid
	 * @param int $shareType
	 */
	public function userDeleted($uid, $shareType) {
		error_log("SMSP: userDeleted!");

		//TODO: probabaly a good idea to send unshare info to remote servers

		$qb = $this->dbConnection->getQueryBuilder();

		$qb->delete('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_REMOTE)))
			->andWhere($qb->expr()->eq('uid_owner', $qb->createNamedParameter($uid)))
			->execute();
	}

	/**
	 * This provider does not handle groups
	 *
	 * @param string $gid
	 */
	public function groupDeleted($gid) {
		error_log("SMSP: groupDeleted!");

		// We don't handle groups here
	}

	/**
	 * This provider does not handle groups
	 *
	 * @param string $uid
	 * @param string $gid
	 */
	public function userDeletedFromGroup($uid, $gid) {
		error_log("SMSP: userDeletedFromGroup!");

		// We don't handle groups here
	}

	/**
	 * check if users from other Nextcloud instances are allowed to mount public links share by this instance
	 *
	 * @return bool
	 */
	public function isOutgoingServer2serverShareEnabled() {
		error_log("SMSP: isOutgoingServer2serverShareEnabled!");

		if ($this->gsConfig->onlyInternalFederation()) {
			return false;
		}
		$result = $this->config->getAppValue('files_sharing', 'outgoing_server2server_share_enabled', 'yes');
		return ($result === 'yes');
	}

	/**
	 * check if users are allowed to mount public links from other Nextclouds
	 *
	 * @return bool
	 */
	public function isIncomingServer2serverShareEnabled() {
		error_log("isIncomingServer2serverShareEnabled");
		if ($this->gsConfig->onlyInternalFederation()) {
			return false;
		}
		$result = $this->config->getAppValue('files_sharing', 'incoming_server2server_share_enabled', 'yes');
		return ($result === 'yes');
	}


	/**
	 * check if users from other Nextcloud instances are allowed to send federated group shares
	 *
	 * @return bool
	 */
	public function isOutgoingServer2serverGroupShareEnabled() {
		error_log("isOutgoingServer2serverGroupShareEnabled");
		if ($this->gsConfig->onlyInternalFederation()) {
			return false;
		}
		$result = $this->config->getAppValue('files_sharing', 'outgoing_server2server_group_share_enabled', 'no');
		return ($result === 'yes');
	}

	/**
	 * check if users are allowed to receive federated group shares
	 *
	 * @return bool
	 */
	public function isIncomingServer2serverGroupShareEnabled() {
		error_log("isIncomingServer2serverGroupShareEnabled");
		if ($this->gsConfig->onlyInternalFederation()) {
			return false;
		}
		$result = $this->config->getAppValue('files_sharing', 'incoming_server2server_group_share_enabled', 'no');
		return ($result === 'yes');
	}

	/**
	 * check if federated group sharing is supported, therefore the OCM API need to be enabled
	 *
	 * @return bool
	 */
	public function isFederatedGroupSharingSupported() {
		error_log("isFederatedGroupSharingSupported");

	    //***
        return $this->cloudFederationProviderManager->isReady();
	}

	/**
	 * Check if querying sharees on the lookup server is enabled
	 *
	 * @return bool
	 */
	public function isLookupServerQueriesEnabled() {
		error_log("isLookupServerQueriesEnabled");
		// in a global scale setup we should always query the lookup server
		if ($this->gsConfig->isGlobalScaleEnabled()) {
			return true;
		}
		$result = $this->config->getAppValue('files_sharing', 'lookupServerEnabled', 'yes');
		return ($result === 'yes');
	}


	/**
	 * Check if it is allowed to publish user specific data to the lookup server
	 *
	 * @return bool
	 */
	public function isLookupServerUploadEnabled() {
		error_log("isLookupServerUploadEnabled");
		// in a global scale setup the admin is responsible to keep the lookup server up-to-date
		if ($this->gsConfig->isGlobalScaleEnabled()) {
			return false;
		}
		$result = $this->config->getAppValue('files_sharing', 'lookupServerUploadEnabled', 'yes');
		return ($result === 'yes');
	}

	/**
	 * @inheritdoc
	 */
	public function getAccessList($nodes, $currentAccess) {
		error_log("SMSP: getAccessList");
		$ids = [];
		foreach ($nodes as $node) {
			$ids[] = $node->getId();
		}

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('share_with', 'token', 'file_source')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_REMOTE)))
			->andWhere($qb->expr()->in('file_source', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));
		$cursor = $qb->execute();

		if ($currentAccess === false) {
			$remote = $cursor->fetch() !== false;
			$cursor->closeCursor();

			return ['remote' => $remote];
		}

		$remote = [];
		while ($row = $cursor->fetch()) {
			$remote[$row['share_with']] = [
				'node_id' => $row['file_source'],
				'token' => $row['token'],
			];
		}
		$cursor->closeCursor();

		return ['remote' => $remote];
	}

	public function getAllShares(): iterable {
		error_log("SMSP: getAllShares");

		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_REMOTE))
			);
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$share = $this->createShareObject($data);
			} catch (InvalidShare $e) {
				continue;
			} catch (ShareNotFound $e) {
				continue;
			}

			yield $share;
		}
		$cursor->closeCursor();
	}

	public function getSentShares($userId): iterable {
		error_log("SMSP: getSentShares");

		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_REMOTE))
			)
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_owner',$qb->createNamedParameter($userId))
				)
			);

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$share = $this->createShareObject($data);
			} catch (InvalidShare $e) {
				continue;
			} catch (ShareNotFound $e) {
				continue;
			}

			yield $share;
		}
		$cursor->closeCursor();
	}

	public function getReceivedShares($userId): iterable {
		error_log("SMSP: getReceivedShares");

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share_external')
			->where(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_REMOTE))
			)
			->andWhere(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId))
			);
		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$share = $this->createExternalShareObject($data);
			} catch (InvalidShare $e) {
				continue;
			} catch (ShareNotFound $e) {
				continue;
			}

			yield $share;
		}
		$cursor->closeCursor();
	}

	public function deleteSentShareByName($userId, $name) {
		error_log("SMSP: deleteSentShareByName");

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('fileid')
			->from('filecache')
			->where(
				$qb->expr()->eq('name', $qb->createNamedParameter($name))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		if (!$data) {
			return false;
		}
		$id = $data['fileid'];
		$isShare = $qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId))
			)
			->andWhere(
				$qb->expr()->eq('item_source', $qb->createNamedParameter($id))
			)
			->execute()
			->fetch();
		if ($isShare) {
			$qb->delete('share')
				->where(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId))
				)
				->andWhere(
					$qb->expr()->eq('item_source', $qb->createNamedParameter($id))
				);
			$qb->execute();
			return true;
		}
		return false;
	}
	public function deleteReceivedShareByOpaqueId($userId, $opaqueId) {
		error_log("SMSP: deleteReceivedShareByOpaqueId");

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share_external')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId))
			)
			->andWhere(
				$qb->expr()->eq('share_token', $qb->createNamedParameter($opaqueId))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		if (!$data) {
			return false;
		} else {
			$qb->delete('share_external')
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($userId))
				)
				->andWhere(
					$qb->expr()->eq('share_token', $qb->createNamedParameter($opaqueId))
				);
			$cursor = $qb->execute();
			return true;
		}
	}
	public function getSentShareByPath($userId, $path) {
		error_log("SMSP: getSentShareByPath");
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('fileid')
			->from('filecache')
			->where(
				$qb->expr()->eq('path', $qb->createNamedParameter($path))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		if (!$data) {
			return false;
		}
		$id = $data['fileid'];
		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId))
			)
			->andWhere(
				$qb->expr()->eq('item_source', $qb->createNamedParameter($id))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		if (!$data) {
			return false;
		}
		try {
			$share = $this->createShareObject($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}
		$cursor->closeCursor();
		return $share;
	}
	public function getShareByOpaqueId($opaqueId) {
		error_log("SMSP: getShareByOpaqueId");

		$qb = $this->dbConnection->getQueryBuilder();
		$c = $qb->select('is_external')
			->from('sciencemesh_shares')
			->where(
				$qb->expr()->eq('opaque_id', $qb->createNamedParameter($opaqueId))
			)
			->execute();
		$data = $c->fetch();
		if (!$data) {
			return false;
		}
		$external = $data['is_external'];
		$c = $qb->select('*')
			->from('sciencemesh_shares', 'sms')
			->innerJoin('sms',$external?'share_external':'share','s',$qb->expr()->eq('sms.foreignId','s.id'))
			->where(
				$qb->expr()->eq('sms.opaque_id', $qb->createNamedParameter($opaqueId))
			)
			->execute();
		$data = $c->fetch();
		if (!$data) {
			return false;
		}
		// FIXME: side effect?
		$res = $external?$this->createScienceMeshExternalShare($data):$this->createScienceMeshShare($data);
		return $res;
	}

	public function addScienceMeshUser($user) {
		error_log("SMSP: addScienceMeshUser");

		$idp = $user->getIdp();
		$opaqueId = $user->getOpaqueId();
		$type = $user->getType();
		$qb = $this->dbConnection->getQueryBuilder();
		$cursor = $qb->select('*')
			->from('sciencemesh_users')
			->where(
				$qb->expr()->eq('idp', $qb->createNamedParameter($idp))
			)
			->andWhere(
				$qb->expr()->eq('opaque_id', $qb->createNamedParameter($opaqueId))
			)
			->execute();
		$data = $cursor->fetch();
		if (!$data) {
			$qb->insert('sciencemesh_users')
				->setValue('idp', $qb->createNamedParameter($idp))
				->setValue('opaque_id', $qb->createNamedParameter($opaqueId))
				->setValue('type', $qb->createNamedParameter($type))
				->execute();
			return $qb->getLastInsertId();
		} else {
			return $data['id'];
		}
	}

	public function addScienceMeshShare($scienceMeshData, $shareData) {
		error_log("SMSP: addScienceMeshShare");

		if ($scienceMeshData['is_external']) {
			return $this->addReceivedShareToDB($shareData);
		} else {
			return  $this->createScienceMeshShare($shareData);
		}
	}

    public function getAllSharesBy($userId, $shareTypes, $nodeIDs, $reshares)
    {
			error_log("SMSP: getAllSharesBy");

        $shares = [];

        $qb = $this->dbConnection->getQueryBuilder();
        $qb->select('*')
            ->from("share");

        // In federated sharing currently we have only one share_type_remote
        $qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_REMOTE)));

        $qb->andWhere($qb->expr()->in('file_source', $qb->createParameter('file_source_ids')));

        /**
         * Reshares for this user are shares where they are the owner.
         */
        if ($reshares === false) {
            //Special case for old shares created via the web UI
            $or1 = $qb->expr()->andX(
                $qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
                $qb->expr()->isNull('uid_initiator')
            );

            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)),
                    $or1
                )
            );
        } else {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
                    $qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
                )
            );
        }

        $qb->orderBy('id');

        $nodeIdsChunks = \array_chunk($nodeIDs, 900);
        foreach ($nodeIdsChunks as $nodeIdsChunk) {
            $qb->setParameter('file_source_ids', $nodeIdsChunk, IQueryBuilder::PARAM_INT_ARRAY);

            $cursor = $qb->execute();
            while ($data = $cursor->fetch()) {
                $shares[] = $this->createShareObject($data);
            }
            $cursor->closeCursor();
        }

        return $shares;
    }

    public function getAllSharedWith($userId, $node)
    {
			error_log("getAllSharedWith");
        return $this->getSharedWith($userId, \OCP\Share::SHARE_TYPE_REMOTE, $node, -1, 0);
    }

    public function getSharesWithInvalidFileid(int $limit)
    {
			error_log("SMSP: getSharesWithInvalidFileid");
        $validShareTypes = [
            \OCP\Share::SHARE_TYPE_REMOTE,
        ];
        // other share types aren't handled by this provider

        $qb = $this->dbConnection->getQueryBuilder();

        $qb = $qb->select('s.*')
            ->from('share', 's')
            ->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
            ->where($qb->expr()->isNull('f.fileid'))
            ->andWhere(
                $qb->expr()->in(
                    'share_type',
                    $qb->createNamedParameter($validShareTypes, IQueryBuilder::PARAM_INT_ARRAY)
                )
            )
            ->orderBy('s.id');

        if ($limit >= 0) {
            $qb->setMaxResults($limit);
        }
        $cursor = $qb->execute();

        $shares = [];
        while ($data = $cursor->fetch()) {
            $shares[] = $this->createShareObject($data);
        }
        $cursor->closeCursor();

        return $shares;
    }

    public function updateForRecipient(IShare $share, $recipient)
    {
			error_log("SMSP: updateForRecipient");
       return $share;
    }

    public function getProviderCapabilities()
    {
			error_log("SMSP: getProviderCapabilities");
       return[
           "sciencemesh" => [
               self::CAPABILITY_STORE_EXPIRATION,
               self::CAPABILITY_STORE_PASSWORD
           ]
       ];
    }
}
