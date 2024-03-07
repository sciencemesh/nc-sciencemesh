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

namespace OCA\ScienceMesh\Plugins;

use OCA\ScienceMesh\RevaHttpClient;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\ISearchResult;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IShare;

class ScienceMeshSearchPlugin implements ISearchPlugin
{
	protected bool $shareeEnumeration;
	/** @var IConfig */
	private IConfig $config;

	/** @var IUserManager */
	private IUserManager $userManager;

	/** @var RevaHttpClient */
	private RevaHttpClient $revaHttpClient;

	/** @var string */
	private string $userId = '';

	public function __construct(IConfig $config, IUserManager $userManager, IUserSession $userSession)
	{
		$this->config = $config;
		$this->userManager = $userManager;
		$user = $userSession->getUser();
		if ($user !== null) {
			$this->userId = $user->getUID();
		}
		$this->shareeEnumeration = $this->config->getAppValue("core", "shareapi_allow_share_dialog_user_enumeration", "yes") === "yes";
		$this->revaHttpClient = new RevaHttpClient($this->config);
	}

	public function search($search, $limit, $offset, ISearchResult $searchResult): bool
	{
		$result = json_decode($this->revaHttpClient->findAcceptedUsers($this->userId), true);
		if (!isset($result)) {
			$resultType = new SearchResultType("remotes");
			$searchResult->addResultSet($resultType, [], []);
			return true;
		}

		error_log("Found " . count($result) . " users");

		$users = array_filter($result, function ($user) use ($search) {
			return (stripos($user["display_name"], $search) !== false);
		});
		$users = array_slice($users, $offset, $limit);

		$result = [];
		foreach ($users as $user) {
			$domain = (str_starts_with($user["idp"], "http") ? parse_url($user["idp"])["host"] : $user["idp"]);
			$result[] = [
				"label" => "Label",
				"uuid" => $user["user_id"],
				"name" => $user["display_name"] . "@" . $domain,
				"type" => "ScienceMesh",
				"value" => [
					"shareType" => IShare::TYPE_SCIENCEMESH,
					"shareWith" => $user["user_id"] . "@" . $domain,
					"server" => $user["idp"]
				]
			];
		}

		$result = [
			"wide" => [],
			"exact" => $result
		];

		$resultType = new SearchResultType("remotes");
		$searchResult->addResultSet($resultType, $result["wide"], $result["exact"]);
		return true;
	}
}
