<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Yvo Brevoort <yvo@muze.nl>
 * @author Benz Schenk <benz.schenk@brokkoli.be>
 * @author Michiel De Jong <michiel@pondersource.com>
 * @author Triantafullenia-Doumani <triantafyllenia@tuta.io>
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\ShareProvider;

use DateTime;
use Exception;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Share;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\FederatedFileSharing\Notifications;
use OCA\FederatedFileSharing\TokenHandler;
use OCA\ScienceMesh\AppInfo\ScienceMeshApp;
use OCA\ScienceMesh\RevaHttpClient;
use OCP\Constants;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Share\Exceptions\IllegalIDChangeException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ScienceMeshShareProvider
 *
 * @package OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider
 */
class ScienceMeshShareProvider extends FederatedShareProviderCopy
{

    /** @var RevaHttpClient */
    protected RevaHttpClient $revaHttpClient;

    /** @var array */
    protected array $supportedShareType;

    /**
     * ScienceMeshShareProvider constructor.
     *
     * @param IDBConnection $connection
     * @param EventDispatcherInterface $eventDispatcher
     * @param AddressHandler $addressHandler
     * @param Notifications $notifications
     * @param TokenHandler $tokenHandler
     * @param IL10N $l10n
     * @param ILogger $logger
     * @param IRootFolder $rootFolder
     * @param IConfig $config
     * @param IUserManager $userManager
     * @throws Exception
     */
    public function __construct(
        IDBConnection            $connection,
        EventDispatcherInterface $eventDispatcher,
        AddressHandler           $addressHandler,
        Notifications            $notifications,
        TokenHandler             $tokenHandler,
        IL10N                    $l10n,
        ILogger                  $logger,
        IRootFolder              $rootFolder,
        IConfig                  $config,
        IUserManager             $userManager
    )
    {
        parent::__construct(
            $connection,
            $eventDispatcher,
            $addressHandler,
            $notifications,
            $tokenHandler,
            $l10n,
            $logger,
            $rootFolder,
            $config,
            $userManager
        );

        $this->supportedShareType[] = ScienceMeshApp::SHARE_TYPE_SCIENCEMESH;
        $this->revaHttpClient = new RevaHttpClient($config);
    }

    /**
     * Check if a given share type is supported by this provider.
     *
     * @param int $shareType
     *
     * @return boolean
     */
    public function isShareTypeSupported(int $shareType): bool
    {
        return in_array($shareType, $this->supportedShareType);
    }

    /**
     * Share a path
     *
     * @param IShare $share
     * @return IShare The share object
     * @throws ShareNotFound
     * @throws Exception
     */
    public function createInternal(IShare $share): IShare
    {
        error_log("SMSP: createInternal");
        $shareWith = $share->getSharedWith();

        error_log("shareWith $shareWith");

        error_log("checking if already shared " . $share->getNode()->getName());
        /*
         * Check if file is not already shared with the remote user
         */
        $alreadyShared = $this->getSharedWith($shareWith, $share->getShareType(), $share->getNode(), 1, 0);
        if (!empty($alreadyShared)) {
            $message = 'Sharing %1$s failed, because this item is already shared with %2$s';
            $message_t = $this->l->t('Sharing %1$s failed, because this item is already shared with user %2$s', [$share->getNode()->getName(), $shareWith]);
            $this->logger->debug(sprintf($message, $share->getNode()->getName(), $shareWith), ['app' => 'ScienceMesh']);
            throw new Exception($message_t);
        }


        // FIXME: don't allow ScienceMesh shares if source and target server are the same
        // ScienceMesh shares always have read permissions
        if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
            $message = 'ScienceMesh shares require read permissions';
            $message_t = $this->l->t('ScienceMesh shares require read permissions');
            $this->logger->debug($message, ['app' => 'ScienceMesh']);
            throw new Exception($message_t);
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
     * @throws Exception
     */
    protected function createScienceMeshShare(IShare $share): int
    {
        return $this->addSentShareToDB(
            $share->getNodeId(),
            $share->getNodeType(),
            $share->getSharedWith(),
            $share->getSharedBy(),
            $share->getShareOwner(),
            $share->getPermissions(),
            $share->getToken(),
            $share->getShareType()
        );
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
    protected function addSentShareToDB(
        int    $itemSource,
        string $itemType,
        string $shareWith,
        string $sharedBy,
        string $uidOwner,
        int    $permissions,
        string $token,
        int    $shareType
    ): int
    {
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
        return $qb->getLastInsertId();
    }

    /**
     * Share a path
     *
     * @param IShare $share
     * @return IShare The share object
     * @throws ShareNotFound
     * @throws Exception
     */
    public function create(IShare $share): IShare
    {
        $node = $share->getNode();
        $shareWith = $share->getSharedWith();

        // This is the routing flag for sending a share.
        // if the recipient of the share is a sciencemesh contact,
        // the search plugin will mark it by a postfix.
        $isSciencemeshUser = $this->stringEndsWith($shareWith, ScienceMeshApp::SCIENCEMESH_POSTFIX);

        // Based on the flag, the share will be sent through sciencemesh or regular share provider.
        if ($isSciencemeshUser) {
            // remove the postfix flag from the string.
            $shareWith = str_replace(ScienceMeshApp::SCIENCEMESH_POSTFIX, "", $shareWith);

            $pathParts = explode("/", $node->getPath());
            $sender = $pathParts[1];
            $sourceOffset = 3;
            $targetOffset = 3;
            $prefix = "/";
            $suffix = ($node->getType() == "dir" ? "/" : "");

            // "home" is reva's default work space name, prepending that in the source path:
            $sourcePath = $prefix . "home/" . implode("/", array_slice($pathParts, $sourceOffset)) . $suffix;
            $targetPath = $prefix . implode("/", array_slice($pathParts, $targetOffset)) . $suffix;

            // TODO: make a function for below operation. it is used in a lot placed, but incorrectly.
            // it should split username@host into an array of 2 element
            // representing array[0] = username, array[1] = host
            // requirement:
            // handle usernames with multiple @ in them.
            // example: MahdiBaghbani@pondersource@sciencemesh.org
            // username: MahdiBaghbani@pondersource
            // host: sciencemesh.org
            $split_point = '@';
            $parts = explode($split_point, $shareWith);
            $last = array_pop($parts);
            $shareWithParts = array(implode($split_point, $parts), $last);

            $response = $this->revaHttpClient->createShare($sender, [
                'sourcePath' => $sourcePath,
                'targetPath' => $targetPath,
                'type' => $node->getType(),
                'recipientUsername' => $shareWithParts[0],
                'recipientHost' => $shareWithParts[1]
            ]);

            if (!isset($response) || !isset($response->share) || !isset($response->share->owner) || !isset($response->share->owner->idp)) {
                throw new Exception("Unexpected response from reva");
            }

            $share->setId("will-set-this-later");
            $share->setProviderId($response->share->owner->idp);
            $share->setShareTime(new DateTime());
        } else {
            $share = parent::create($share);
        }

        return $share;
    }

    /**
     * Check if a given string ends with a substring.
     *
     * @param string $string
     * @param string $search
     * @return bool
     */
    function stringEndsWith(string $string, string $search): bool
    {
        $length = strlen($search);
        if (!$length) {
            return true;
        }
        return substr($string, -$length) === $search;
    }

    /**
     * Update a received share
     *
     * @param IShare $share
     * @return IShare The share object
     */
    public function updateReceivedShare(IShare $share): IShare
    {
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
     * Get a share by token
     *
     * @param string $token
     * @return IShare
     * @throws ShareNotFound|IllegalIDChangeException
     */
    public function getReceivedShareByToken(string $token)
    {
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
        return $this->createExternalShareObject($data);
    }

    /**
     * Create a share object from a database row from external shares
     *
     * @param array $data
     * @return IShare
     * @throws IllegalIDChangeException
     */
    protected function createExternalShareObject(array $data)
    {
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
     * Return the identifier of this provider.
     *
     * @return string Containing only [a-zA-Z0-9]
     */
    public function identifier(): string
    {
        return 'sciencemesh';
    }

    /**
     * Get a share by token
     *
     * @param string $token
     * @return IShare
     * @throws ShareNotFound|IllegalIDChangeException
     */
    public function getSentShareByToken(string $token): IShare
    {
        error_log("share provider getSentShareByToken '$token'");
        $qb = $this->dbConnection->getQueryBuilder();
        $cursor = $qb->select('*')
            ->from('share')
            ->where($qb->expr()->eq('token', $qb->createNamedParameter($token)))
            ->execute();
        $data = $cursor->fetch();
        if ($data === false) {
            error_log("sent share not found by token '$token'");
            throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
        }
        try {
            $share = $this->createShareObject($data);
        } catch (InvalidShare $e) {
            error_log("sent share found invalid by token '$token'");
            throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
        }
        error_log("found sent share " . $data["id"] . " by token '$token'");
        return $share;
    }

    public function getSentShares(int $userId): iterable
    {
        $qb = $this->dbConnection->getQueryBuilder();

        $qb->select('*')
            ->from('share')
            ->where(
                $qb->expr()->eq('share_type', $qb->createNamedParameter(ScienceMeshApp::SHARE_TYPE_SCIENCEMESH))
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)),
                    $qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId))
                )
            );

        $cursor = $qb->execute();
        while ($data = $cursor->fetch()) {
            try {
                $share = $this->createShareObject($data);
            } catch (InvalidShare|IllegalIDChangeException $e) {
                continue;
            }

            yield $share;
        }
        $cursor->closeCursor();
    }

    public function getReceivedShares($userId): iterable
    {
        $qb = $this->dbConnection->getQueryBuilder();
        $qb->select('*')
            ->from('share_external')
            ->where(
                $qb->expr()->eq('user', $qb->createNamedParameter($userId))
            );
        $cursor = $qb->execute();
        while ($data = $cursor->fetch()) {
            try {
                $share = $this->createExternalShareObject($data);
            } catch (IllegalIDChangeException $e) {
                continue;
            }

            yield $share;
        }
        $cursor->closeCursor();
    }

    public function deleteSentShareByName($userId, $name): bool
    {
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

    /**
     * Delete a share (owner unShares the file)
     *
     * @param IShare $share
     */
    public function delete(IShare $share)
    {
        // only remove the share when all messages are send to not lose information
        // about the share to early
        $this->removeShareFromTable($share);
    }

    public function deleteReceivedShareByOpaqueId($userId, $opaqueId): bool
    {
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

    /**
     * @throws IllegalIDChangeException
     * @throws ShareNotFound
     */
    public function getSentShareByPath($userId, $path)
    {
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

    /**
     * @throws Exception
     */
    public function getShareByOpaqueId($opaqueId)
    {
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
            ->innerJoin('sms', $external ? 'share_external' : 'share', 's', $qb->expr()->eq('sms.foreignId', 's.id'))
            ->where(
                $qb->expr()->eq('sms.opaque_id', $qb->createNamedParameter($opaqueId))
            )
            ->execute();
        $data = $c->fetch();
        if (!$data) {
            return false;
        }

        return $external ? $this->createScienceMeshExternalShare($data) : $this->createScienceMeshShare($data);
    }

    public function addScienceMeshUser($user)
    {
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

    /**
     * @throws Exception
     */
    public function addScienceMeshShare($scienceMeshData, $shareData): int
    {
        if ($scienceMeshData['is_external']) {
            return $this->addReceivedOcmShareToEfssDatabaseTable($shareData);
        } else {
            return $this->createScienceMeshShare($shareData);
        }
    }

    /**
     * add share to the efss database table and return the ID
     *
     * @param $shareData
     * @return int
     */
    public function addReceivedOcmShareToEfssDatabaseTable($shareData): int
    {
        // calculate the mountpoint has of the share.
        $mountpoint = "{{TemporaryMountPointName#" . $shareData["name"] . "}}";
        $mountpoint_hash = md5($mountpoint);

        // check if the share already exists in the database.
        $qbt = $this->dbConnection->getQueryBuilder();
        $qbt->select("*")
            ->from("share_external")
            ->where($qbt->expr()->eq("user", $qbt->createNamedParameter($shareData["user"])))
            ->andWhere($qbt->expr()->eq("mountpoint_hash", $qbt->createNamedParameter($mountpoint_hash)));
        $cursor = $qbt->execute();

        // return id if share already exists.
        if ($data = $cursor->fetch()) {
            return $data["id"];
        }

        // NOTE: @Mahdi I don't like this approach.
        // prefix remote with https if it doesn't start with http or https.
        if (!str_starts_with(strtolower($shareData["remote"]), "http://") && !str_starts_with(strtolower($shareData["remote"]), "https://")) {
            $shareData["remote"] = "https://" . $shareData["remote"];
        }

        // TODO: @Mahdi maybe use enums? for better readability.
        // 0 => pending, 1 => accepted, 2 => rejected
        $accepted = 0;
        $qb = $this->dbConnection->getQueryBuilder();
        $qb->insert("share_external")
            ->setValue('remote', $qb->createNamedParameter($shareData["remote"]))
            ->setValue('remote_id', $qb->createNamedParameter(trim($shareData["remote_id"], '"')))
            ->setValue('share_token', $qb->createNamedParameter($shareData["share_token"]))
            ->setValue('password', $qb->createNamedParameter($shareData["password"]))
            ->setValue('name', $qb->createNamedParameter($shareData["name"]))
            ->setValue('owner', $qb->createNamedParameter($shareData["owner"]))
            ->setValue('user', $qb->createNamedParameter($shareData["user"]))
            ->setValue('mountpoint', $qb->createNamedParameter($mountpoint))
            ->setValue('mountpoint_hash', $qb->createNamedParameter($mountpoint_hash))
            ->setValue('accepted', $qb->createNamedParameter($accepted));
        $qb->execute();
        return $qb->getLastInsertId();
    }

    /**
     * add share to the sciencemesh database table and return the ID
     *
     * @param $shareData
     * @return int
     */
    public function addReceivedOcmShareToSciencemeshDatabaseTable($shareData): int
    {
        // check if the share already exists in the database.
        $qbt = $this->dbConnection->getQueryBuilder();
        $qbt->select("*")
            ->from("sciencemesh_ocm_received_shares")
            ->where($qbt->expr()->eq("share_external_id", $qbt->createNamedParameter($shareData["share_external_id"])));
        $cursor = $qbt->execute();

        // return id if share already exists.
        if ($data = $cursor->fetch()) {
            return $data["id"];
        }

        // add ocm share to sciencemesh_ocm_received_shares table.
        $qb = $this->dbConnection->getQueryBuilder();
        $qb->insert("sciencemesh_ocm_received_shares")
            ->setValue("share_external_id", $qb->createNamedParameter($shareData["share_external_id"]))
            ->setValue("name", $qb->createNamedParameter($shareData["name"]))
            ->setValue("item_type", $qb->createNamedParameter($shareData["item_type"]))
            ->setValue("share_with", $qb->createNamedParameter($shareData["share_with"]))
            ->setValue("owner", $qb->createNamedParameter($shareData["owner"]))
            ->setValue("initiator", $qb->createNamedParameter($shareData["initiator"]))
            ->setValue("ctime", $qb->createNamedParameter($shareData["ctime"]))
            ->setValue("ctime", $qb->createNamedParameter($shareData["ctime"]))
            ->setValue("mtime", $qb->createNamedParameter($shareData["mtime"]))
            ->setValue("expiration", $qb->createNamedParameter($shareData["expiration"]))
            ->setValue("type", $qb->createNamedParameter($shareData["type"]))
            ->setValue("state", $qb->createNamedParameter($shareData["state"]))
            ->setValue("remote_share_id", $qb->createNamedParameter($shareData["remote_share_id"]));
        $qb->execute();

        $id = $qb->getLastInsertId();

        // add ocm share id to protocols table.
        $qb = $this->dbConnection->getQueryBuilder();
        $qb->insert("sciencemesh_ocm_received_share_protocols")
            ->setValue("ocm_received_share_id", $qb->createNamedParameter($id))
            ->setValue("type", $qb->createNamedParameter($shareData["type"]));
        $qb->execute();

        $protocolsID = $qb->getLastInsertId();

        // add protocols to their tables.
        foreach ($shareData["transfers"] as $transfer) {
            if (isset($transfer["sourceUri"]) && $transfer["sharedSecret"] && $transfer["size"]) {
                $qb = $this->dbConnection->getQueryBuilder();
                $qb->insert("sciencemesh_ocm_protocol_transfer")
                    ->setValue("ocm_protocol_id", $qb->createNamedParameter($protocolsID))
                    ->setValue("source_uri", $qb->createNamedParameter($transfer["sourceUri"]))
                    ->setValue("shared_secret", $qb->createNamedParameter($transfer["sharedSecret"]))
                    ->setValue("size", $qb->createNamedParameter($transfer["size"]));
                $qb->execute();
            }
        }

        foreach ($shareData["webapps"] as $webapp) {
            if (isset($webapp["uriTemplate"]) && $webapp["viewMode"]) {
                $qb = $this->dbConnection->getQueryBuilder();
                $qb->insert("sciencemesh_ocm_protocol_webapp")
                    ->setValue("ocm_protocol_id", $qb->createNamedParameter($protocolsID))
                    ->setValue("uri_template", $qb->createNamedParameter($webapp["uriTemplate"]))
                    ->setValue("view_mode", $qb->createNamedParameter($webapp["viewMode"]));
                $qb->execute();
            }
        }

        foreach ($shareData["webdavs"] as $webdav) {
            if (isset($webdav["uri"]) && $webdav["sharedSecret"] && $webdav["permissions"]) {
                $qb = $this->dbConnection->getQueryBuilder();
                $qb->insert("sciencemesh_ocm_protocol_webdav")
                    ->setValue("ocm_protocol_id", $qb->createNamedParameter($protocolsID))
                    ->setValue("uri", $qb->createNamedParameter($webdav["uri"]))
                    ->setValue("shared_secret", $qb->createNamedParameter($webdav["sharedSecret"]))
                    ->setValue("permissions", $qb->createNamedParameter($webdav["permissions"]));
                $qb->execute();
            }
        }

        return $id;
    }

    protected function revokeShare(IShare $share, bool $isOwner)
    {
        if ($this->userManager->userExists($share->getShareOwner()) && $this->userManager->userExists($share->getSharedBy())) {
            // If both the owner and the initiator of the share are local users we don't have to notify anybody else
            return;
        }

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
}
