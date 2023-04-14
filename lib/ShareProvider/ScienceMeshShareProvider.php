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

use OC\Share20\Exception\InvalidShare;
use OC\Share20\Share;
use OCP\Constants;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCA\ScienceMesh\RevaHttpClient;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\FederatedFileSharing\FederatedShareProvider;

/**
 * Class ScienceMeshShareProvider
 *
 * @package OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider
 */
class ScienceMeshShareProvider extends FederatedShareProvider {
	public const SHARE_TYPE_REMOTE = 6;

    // private properties are not inherited from parent class. so I have to declare them again
    // weird since I learned OOP with Python.
	/** @var IDBConnection */
	private $dbConnection;

	/** @var TokenHandler */
	private $tokenHandler;

	/** @var IL10N */
	private $l;

	/** @var ILogger */
	private $logger;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var \OCP\GlobalScale\IConfig */
	private $gsConfig;

	/** @var array list of supported share types */
	private $supportedShareType = [IShare::TYPE_SCIENCEMESH];

	/** @var RevaHttpClient */
	private $revaHttpClient;

	/**
	 * DefaultShareProvider constructor.
	 *
	 * @param IDBConnection $connection
     * @param AddressHandler $addressHandler
     * @param Notifications $notifications
	 * @param TokenHandler $tokenHandler
	 * @param IL10N $l10n
	 * @param ILogger $logger
	 * @param IRootFolder $rootFolder
	 * @param IConfig $config
	 * @param IUserManager $userManager
     * @param ICloudIdManager $cloudIdManager
	 * @param \OCP\GlobalScale\IConfig $globalScaleConfig
     * @param ICloudFederationProviderManager $cloudFederationProviderManager
	 */
	public function __construct(
			IDBConnection $connection,
            AddressHandler $addressHandler,
            Notifications $notifications,
            TokenHandler $tokenHandler,
			IL10N $l10n,
			ILogger $logger,
			IRootFolder $rootFolder,
			IConfig $config,
			IUserManager $userManager,
            ICloudIdManager $cloudIdManager,
			\OCP\GlobalScale\IConfig $globalScaleConfig,
            ICloudFederationProviderManager $cloudFederationProviderManager
	) {
        parent::__construct(
            $connection,
            $addressHandler,
            $notifications,
            $tokenHandler,
            $l10n,
            $logger,
            $rootFolder,
            $config,
            $userManager,
            $cloudIdManager,
            $globalScaleConfig,
            $cloudFederationProviderManager
        );

		$this->dbConnection = $connection;
		$this->tokenHandler = $tokenHandler;
		$this->l = $l10n;
		$this->logger = $logger;
		$this->rootFolder = $rootFolder;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->gsConfig = $globalScaleConfig;
		$this->revaHttpClient = new RevaHttpClient($this->config);
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
    // TODO: can't find usage.
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
	public function create(IShare $share) {
		$node = $share->getNode();
		$shareWith = $share->getSharedWith();
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

		$share->setId("will-set-this-later");
		$share->setProviderId($response->share->owner->idp);
		$share->setShareTime(new \DateTime());

		return $share;
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
		error_log("SSP create");
		$shareWith = $share->getSharedWith();

		/*
		 * Check if file is not already shared with the remote user
		 */
		$alreadyShared = $this->getSharedWith($shareWith, $this::SHARE_TYPE_REMOTE, $share->getNode(), 1, 0);
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
		$shareId = $this->createScienceMeshShare($share);
		$data = $this->getRawShare($shareId);

		return $this->createShareObject($data);
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
		$token = $share->getToken();
		$shareId = $this->addSentShareToDB(
			$share->getNodeId(),
			$share->getNodeType(),
			$share->getSharedWith(),
			$share->getSharedBy(),
			$share->getShareOwner(),
			$share->getPermissions(),
			$token,
			$this::SHARE_TYPE_REMOTE
		);
		return $shareId;
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
    error_log("addSentShareToDB");
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
    error_log("Created share with id $id");
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
		$share_type = IShare::TYPE_USER;
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
		$accepted = IShare::STATUS_PENDING;
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert('share_external')
			->setValue('share_type', $qb->createNamedParameter($share_type))
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
	 * Update a received share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 */
    // TODO: check before merge: cannot find usage for it.
	public function updateReceivedShare(IShare $share) {
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
	 * Delete a share (owner unShares the file)
	 *
	 * @param IShare $share
	 * @throws ShareNotFound
	 * @throws \OC\HintException
	 */
    // NOTE: this method overrides parent method.
	public function delete(IShare $share) {
		$this->removeShareFromTable($share);
	}

	/**
	 * remove share from table
	 *
	 * @param string $shareId
	 */
    // NOTES: diff with parent class: $this::SHARE_TYPE_REMOTE -> IShare::TYPE_CIRCLE
    private function removeShareFromTableById($shareId) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($shareId)))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter($this::SHARE_TYPE_REMOTE)));
		$qb->execute();

		$qb->delete('federated_reshares')
			->where($qb->expr()->eq('share_id', $qb->createNamedParameter($shareId)));
		$qb->execute();
	}

	/**
	 * Get a share by token
	 *
	 * @param string $token
	 * @return IShare
	 * @throws ShareNotFound
	 */
	public function getReceivedShareByToken($token) {
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
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws InvalidShare
	 */
	private function getNode($userId, $id) {
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

	public function getSentShares($userId): iterable {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->eq('share_type', $qb->createNamedParameter($this::SHARE_TYPE_REMOTE))
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
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share_external')
			->where(
				$qb->expr()->eq('share_type', $qb->createNamedParameter($this::SHARE_TYPE_REMOTE))
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
			$qb->execute();
			return true;
		}
	}

    // TODO: can't find usage.
	public function getSentShareByPath($userId, $path) {
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

    // TODO: can't find usage.
	public function getShareByOpaqueId($opaqueId) {
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

    // TODO: can't find usage.
	public function addScienceMeshUser($user) {
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
		if ($scienceMeshData['is_external']) {
			return $this->addReceivedShareToDB($shareData);
		} else {
			return  $this->createScienceMeshShare($shareData);
		}
	}
}
