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

use OC\Share20\Exception\InvalidShare;
use OC\Share20\Share;
use OCP\Constants;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;

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
	}
}
