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

declare(strict_types=1);

namespace OCA\ScienceMesh\AppInfo;

use OCA\ScienceMesh\Notifier\ScienceMeshNotifier;
use OCA\ScienceMesh\Plugins\ScienceMeshSearchPlugin;
use OCA\ScienceMesh\Service\UserService;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\AppFramework\App;

class ScienceMeshApp extends App
{
	public function __construct()
	{
		parent::__construct("sciencemesh");

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService("UserService", function ($c) {
			return new UserService(
				$c->query("UserSession")
			);
		});
		$container->registerService("UserSession", function ($c) {
			return $c->query("ServerContainer")->getUserSession();
		});

		// currently logged-in user, userId can be gotten by calling the
		// getUID() method on it
		$container->registerService("User", function ($c) {
			return $c->query("UserSession")->getUser();
		});

		$collaboration = $container->get("OCP\Collaboration\Collaborators\ISearch");
		$collaboration->registerPlugin(["shareType" => "SHARE_TYPE_REMOTE", "class" => ScienceMeshSearchPlugin::class]);

		$shareManager = $container->get("OCP\Share\IManager");
		$shareManager->registerShareProvider(ScienceMeshShareProvider::class);

		$notificationManager = $server->getNotificationManager();
		$notificationManager->registerNotifierService(ScienceMeshNotifier::class);
	}
}
