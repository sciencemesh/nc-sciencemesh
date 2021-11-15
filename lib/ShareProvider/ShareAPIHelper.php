<?php
/**
 * @copyright Copyright (c) 2021, PonderSource
 *
 * @author Yvo Brevoort <yvo@pondersource.nl>
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

use OCP\IConfig;
use OCP\IUserManager;
use OCA\ScienceMesh\RevaHttpClient;

/**
 * Class ScienceMeshShareHelper
 *
 * @package OCA\ScienceMesh\ShareProvider\ShareAPIHelper
 */
class ShareAPIHelper {
	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var RevaHttpClient */
	private $revaHttpClient;

	/**
	 * ShareAPIHelper constructor.
	 *
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 */
	public function __construct(
		IConfig $config,
		IUserManager $userManager
	) {
		$this->config = $config;
		$this->userManager = $userManager;
		$this->revaHttpClient = new RevaHttpClient();
	}

	public function formatShare($share) {
		$result = [];
		$result['share_with'] = $share->getSharedWith();
		$result['share_with_displayname'] = $result['share_with'];
		$result['token'] = $share->getToken();
		return $result;
	}
	
	public function createShare($share, $shareWith, $permissions, $expireDate) {
		$share->setSharedWith($shareWith);
		$share->setPermissions($permissions);
		error_log('making rest call to grpc client '. $shareWith);
		$this->revaHttpClient->createShare([
			'path' => '/home',
			'recipientUsername' => 'marie',
			'recipientHost' => 'localhost:17000'
		]);
	}
}
