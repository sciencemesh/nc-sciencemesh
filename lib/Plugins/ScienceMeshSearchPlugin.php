<?php

namespace OCA\ScienceMesh\Plugins;

use OC\User\User;
use OCP\IConfig;
use OCP\Share;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\ScienceMesh\RevaHttpClient;
use OCA\ScienceMesh\AppInfo\ScienceMeshApp;
use OCP\Contacts\IManager;
use OCP\Util\UserSearch;

class ScienceMeshSearchPlugin {
	protected $shareeEnumeration;

	/** @var IManager */
	protected $contactsManager;

	/** @var int */
	protected $offset = 0;

	/** @var int */
	protected $limit = 10;

	/** @var UserSearch*/
	protected $userSearch;

	/** @var IConfig */
	private $config;

	/** @var string */
	private $userId = '';

	public function __construct(IManager $contactsManager, IConfig $config, IUserManager $userManager, IUserSession $userSession, UserSearch $userSearch) {
		$this->config = $config;
		$user = $userSession->getUser();
		$this->contactsManager = $contactsManager;
		$this->userSearch = $userSearch;
		if ($user !== null) {
			$this->userId = $user->getUID();
		}
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->revaHttpClient = new RevaHttpClient($this->config);
	}

	public function search($search) {
		$result = json_decode($this->revaHttpClient->findAcceptedUsers($this->userId), true);
		if (!isset($result['accepted_users'])) {
			return [];
		}
		$users = $result['accepted_users'];
        error_log("Found " . count($users) . " users");

		$result = [];
		foreach ($users as $user) {
			$serverUrl = parse_url($user['id']['idp']);
			$domain = (str_starts_with($user['id']['idp'], "http") ? parse_url($user['id']['idp'])["host"] : $user['id']['idp']);
			$result[] = [
				'label' => $user['display_name'] ." (". $domain . ")",
				'value' => [
					'shareType' => ScienceMeshApp::SHARE_TYPE_SCIENCEMESH,
					'shareWith' => $user['id']['opaque_id'] ."@". $user['id']['idp'],
				],
			];
		}
		error_log("returning result from sciencemesh:");
		error_log(var_export($result, true));

		$otherResults = [];

		$searchProperties = \explode(',', $this->config->getAppValue('dav', 'remote_search_properties', 'CLOUD,FN'));
		// Search in contacts
		$matchMode = $this->config->getSystemValue('accounts.enable_medial_search', true) === true
			? 'ANY'
			: 'START';
		$addressBookContacts = $this->contactsManager->search(
			$search,
			$searchProperties,
			[ 'matchMode' => $matchMode ],
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
				// we need a cloud id to setup a remote share
				continue;
			}

			// we can have multiple cloud domains, always convert to an array
			$cloudIds = $contact['CLOUD'];
			if (!\is_array($cloudIds)) {
				$cloudIds = [$cloudIds];
			}

			$lowerSearch = \strtolower($search);
			foreach ($cloudIds as $cloudId) {
				list(, $serverUrl) = $this->splitUserRemote($cloudId);

				if (\strtolower($cloudId) === $lowerSearch) {
					$foundRemoteById = true;
					// Save this as an exact match and continue with next CLOUD
					$otherResults[] = [
						'label' => $contact['FN'],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
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
						// Skip this property since our contact doesnt have it
						continue;
					}
					// check if we have a match
					$values = $contact[$property];
					if (!\is_array($values)) {
						$values = [$values];
					}
					foreach ($values as $value) {
						// check if we have an exact match
						if (\strtolower($value) === $lowerSearch) {
							$this->result['exact']['remotes'][] = [
								'label' => $contact['FN'],
								'value' => [
									'shareType' => Share::SHARE_TYPE_REMOTE,
									'shareWith' => $cloudId,
									'server' => $serverUrl,
								],
							];

							// Now skip to next CLOUD
							continue 3;
						}
					}
				}

				// If we get here, we didnt find an exact match, so add to other matches
				if ($this->userSearch->isSearchable($search)) {
					$otherResults[] = [
						'label' => $contact['FN'],
						'value' => [
							'shareType' => Share::SHARE_TYPE_REMOTE,
							'shareWith' => $cloudId,
							'server' => $serverUrl,
						],
					];
				}
			}
		}

		// remove the exact user results if we dont allow autocomplete
		if (!$this->shareeEnumeration) {
			$otherResults = [];
		}

		if (!$foundRemoteById && \substr_count($search, '@') >= 1
			&& $this->offset === 0 && $this->userSearch->isSearchable($search)
			// if an exact local user is found, only keep the remote entry if
			// its domain does not match the trusted domains
			// (if it does, it is a user whose local login domain matches the ownCloud
			// instance domain)
			&& (empty($this->result['exact']['users'])
				|| !$this->isInstanceDomain($search))
		) {
			$otherResults[] = [
				'label' => $search,
				'value' => [
					'shareType' => Share::SHARE_TYPE_REMOTE,
					'shareWith' => $search,
				],
			];
		}

		error_log("returning other results:");
		error_log(var_export($otherResults, true));

		$result = array_merge($result, $otherResults);

		return $result;
	}
}
