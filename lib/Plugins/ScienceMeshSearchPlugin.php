<?php

namespace OCA\ScienceMesh\Plugins;

use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\ScienceMesh\RevaHttpClient;

class ScienceMeshSearchPlugin implements ISearchPlugin {
	protected $shareeEnumeration;
	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var string */
	private $userId = '';

	public function __construct(IConfig $config, IUserManager $userManager, IUserSession $userSession) {
		$this->config = $config;
		$this->userManager = $userManager;
		$user = $userSession->getUser();
		if ($user !== null) {
			$this->userId = $user->getUID();
		}
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->revaHttpClient = new RevaHttpClient($this->config);
	}

	public function search($search, $limit, $offset, ISearchResult $searchResult) {
		error_log("getting accepted users query '$search' '$limit' '$offset'");
		$result = json_decode($this->revaHttpClient->findAcceptedUsers($this->userId), true);
		error_log('getting accepted users result:');
		error_log(var_export($result, true));
		if (!isset($result['accepted_users'])) {
      error_log("none found!");
			return;
		}
		error_log("found some!");
		$users = $result['accepted_users'];
		error_log("users found:");
		error_log(var_export($users, true));

		$users = array_filter($users, function ($user) use ($search) {
			error_log("filtering on display name '" . $user['display_name'] . "' ?= '" . $search . "'");
			return (stripos($user['display_name'], $search) !== false);
		});
		$users = array_slice($users, $offset, $limit);

		$exactResults = [];
		foreach ($users as $user) {
			error_log("producing exact result from:");
			error_log(var_export($user, true));
			$exactResults[] = [
				"label" => "Label",
				"uuid" => $user['id']['opaque_id'],
				"name" => $user['display_name'],
				"type" => "ScienceMesh",
				"value" => [
					"shareType" => 1000, // FIXME: Replace with SHARE_TYPE_SCIENCEMESH
					"shareWith" => $user['id']['opaque_id'],
					"server" => $user['id']['idp']
				]
			];
		}

		$result = [
			'wide' => [],
			'exact' => $exactResults
		];

		$resultType = new SearchResultType('remotes');
		$searchResult->addResultSet($resultType, $result['wide'], $result['exact']);
		return true;
	}
}
