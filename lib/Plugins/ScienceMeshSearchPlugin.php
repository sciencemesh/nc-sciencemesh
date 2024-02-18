<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Michiel De Jong <michiel@pondersource.com>
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\Plugins;

use Exception;
use OC\Share\Constants;
use OCA\ScienceMesh\AppInfo\ScienceMeshApp;
use OCA\ScienceMesh\RevaHttpClient;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCA\ScienceMesh\Utils\UtilsSmShareProvider;
use OCP\Contacts\IManager;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserSession;
use OCP\Util\UserSearch;
use function explode;
use function is_array;
use function strtolower;
use function substr_count;

class ScienceMeshSearchPlugin
{
    protected bool $shareeEnumeration;

    /** @var IManager */
    protected IManager $contactsManager;

    /** @var int */
    protected int $offset = 0;

    /** @var int */
    protected int $limit = 10;

    /** @var UserSearch */
    protected UserSearch $userSearch;

    /** @var IConfig */
    private IConfig $config;

    /** @var string */
    private string $userId = '';

    /** @var RevaHttpClient */
    private RevaHttpClient $revaHttpClient;

    /** @var IL10N */
    private IL10N $l;

    /** @var ILogger */
    private ILogger $logger;

    /** @var UtilsSmShareProvider */
    private UtilsSmShareProvider $utils;

    /**
     * @throws Exception
     */
    public function __construct(
        IManager                 $contactsManager,
        IConfig                  $config,
        IUserSession             $userSession,
        UserSearch               $userSearch,
        IL10N                    $l10n,
        ILogger                  $logger,
        ScienceMeshShareProvider $shareProvider
    )
    {
        $this->config = $config;
        $user = $userSession->getUser();
        $this->contactsManager = $contactsManager;
        $this->userSearch = $userSearch;
        if ($user !== null) {
            $this->userId = $user->getUID();
        }
        $this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
        $this->revaHttpClient = new RevaHttpClient($this->config);
        $this->l = $l10n;
        $this->logger = $logger;
        $this->utils = new UtilsSmShareProvider($l10n, $logger, $shareProvider);
    }

    /**
     * @throws Exception
     */
    public function search($search): array
    {
        $result = json_decode($this->revaHttpClient->findAcceptedUsers($this->userId), true);
        if (!isset($result)) {
            return [];
        }
        $users = $result;
        error_log("Found " . count($users) . " users");

        $result = [];
        foreach ($users as $user) {
            $domain = (str_starts_with($user['idp'], "http") ? parse_url($user['idp'])["host"] : $user['idp']);
            $result[] = [
                'label' => $user['display_name'] . " (" . $domain . ")",
                'value' => [
                    'shareType' => ScienceMeshApp::SHARE_TYPE_SCIENCEMESH,
                    'shareWith' => $user['user_id'] . "@" . $user['idp'] . ScienceMeshApp::SCIENCEMESH_POSTFIX,
                ],
            ];
        }

        $otherResults = [];

        $searchProperties = explode(',', $this->config->getAppValue('dav', 'remote_search_properties', 'CLOUD,FN'));
        // Search in contacts
        $matchMode = $this->config->getSystemValue('accounts.enable_medial_search', true) === true
            ? 'ANY'
            : 'START';
        $addressBookContacts = $this->contactsManager->search(
            $search,
            $searchProperties,
            ['matchMode' => $matchMode],
            $this->limit,
            $this->offset
        );
        $foundRemoteById = false;
        foreach ($addressBookContacts as $contact) {
            if (isset($contact['isLocalSystemBook'])) {
                // We only want remote users
                continue;
            }
            if (!isset($contact['CLOUD'])) {
                // we need a cloud id to set up a remote share
                continue;
            }

            // we can have multiple cloud domains, always convert to an array
            $cloudIds = $contact['CLOUD'];
            if (!is_array($cloudIds)) {
                $cloudIds = [$cloudIds];
            }

            $lowerSearch = strtolower($search);
            foreach ($cloudIds as $cloudId) {
                list(, $serverUrl) = $this->utils->splitUserRemote($cloudId);

                if (strtolower($cloudId) === $lowerSearch) {
                    $foundRemoteById = true;
                    // Save this as an exact match and continue with next CLOUD
                    $otherResults[] = [
                        'label' => $contact['FN'],
                        'value' => [
                            'shareType' => Constants::SHARE_TYPE_REMOTE,
                            'shareWith' => $cloudId,
                            'server' => $serverUrl,
                        ],
                    ];
                    continue;
                }

                // CLOUD matching is done above
                unset($searchProperties['CLOUD']);
                foreach ($searchProperties as $property) {
                    // do we even have this property for this contact/
                    if (!isset($contact[$property])) {
                        // Skip this property since our contact doesn't have it
                        continue;
                    }
                    // check if we have a match
                    $values = $contact[$property];
                    if (!is_array($values)) {
                        $values = [$values];
                    }
                    foreach ($values as $value) {
                        // check if we have an exact match
                        if (strtolower($value) === $lowerSearch) {
                            $this->result['exact']['remotes'][] = [
                                'label' => $contact['FN'],
                                'value' => [
                                    'shareType' => Constants::SHARE_TYPE_REMOTE,
                                    'shareWith' => $cloudId,
                                    'server' => $serverUrl,
                                ],
                            ];

                            // Now skip to next CLOUD
                            continue 3;
                        }
                    }
                }

                // If we get here, we didn't find an exact match, so add to other matches
                if ($this->userSearch->isSearchable($search)) {
                    $otherResults[] = [
                        'label' => $contact['FN'],
                        'value' => [
                            'shareType' => Constants::SHARE_TYPE_REMOTE,
                            'shareWith' => $cloudId,
                            'server' => $serverUrl,
                        ],
                    ];
                }
            }
        }

        // remove the exact user results if we don't allow autocomplete
        if (!$this->shareeEnumeration) {
            $otherResults = [];
        }

        if (!$foundRemoteById && substr_count($search, '@') >= 1
            && $this->offset === 0 && $this->userSearch->isSearchable($search)
            // if an exact local user is found, only keep the remote entry if
            // its domain does not match the trusted domains
            // (if it does, it is a user whose local login domain matches the ownCloud
            // instance domain)
            && (empty($this->result['exact']['users'])
                || !$this->utils->isInstanceDomain($search))
        ) {
            $otherResults[] = [
                'label' => $search,
                'value' => [
                    'shareType' => Constants::SHARE_TYPE_REMOTE,
                    'shareWith' => $search,
                ],
            ];
        }

        $result = array_merge($result, $otherResults);

        if (count($result) > 0)
            return $result;
        return [];
    }
}
