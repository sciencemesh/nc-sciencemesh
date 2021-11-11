<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\ScienceMesh\Notifier;

use OCP\IURLGenerator;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class ScienceMeshNotifier implements INotifier {

	/** @var IURLGenerator */
	protected $urlGenerator;

	/**
	 * @param IFactory $l10nFactory
	 * @param IURLGenerator $urlGenerator
	 */
	public function __construct(IURLGenerator $urlGenerator) {
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'sciencemesh';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return "sciencemesh";
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @throws AlreadyProcessedException When the notification is not needed anymore and should be deleted
	 */

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'sciencemesh') {
			throw new \InvalidArgumentException('Unknown app');
		}

		switch ($notification->getSubject()) {
			// Deal with known subjects
			case 'remote_share':
				$subjectParams = $notification->getSubjectParameters();
				if (!isset($subjectParams[0])) {
					$notification->setParsedSubject("ScienceMesh notification");
				} else {
					$notification->setParsedSubject($subjectParams[0]);
				}
				$messageParams = $notification->getMessageParameters();
				if (isset($messageParams[0]) && $messageParams[0] !== '') {
					$notification->setParsedMessage($messageParams[0]);
				}
//				$notification->setParsedMessage(json_encode($subjectParams));
				$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('notifications', 'notifications-dark.svg')));

				// Deal with the actions for a known subject
				foreach ($notification->getActions() as $action) {
					switch ($action->getLabel()) {
						case 'accept':
							$action->setParsedLabel("Accept")
								->setLink($this->urlGenerator->linkToRouteAbsolute('sciencemesh.app.shared'), 'GET');
							//	->setLink($this->urlGenerator->linkToRouteAbsolute('sciencemesh.app.shared'), 'GET'); // , ['id' => $notification->getObjectId()]), 'POST');
							break;
						case 'decline':
							$action->setParsedLabel("Decline")
								->setLink($this->urlGenerator->linkToRouteAbsolute('sciencemesh.app.shared'), 'GET');
							//	->setLink($this->urlGenerator->linkToRouteAbsolute('sciencemesh.app.shared'), 'GET'); // , ['id' => $notification->getObjectId()]), 'DELETE');
							break;
					}

					$notification->addParsedAction($action);
				}

				return $notification;

			default:
				throw new \InvalidArgumentException('Unknown subject');
		}
	}
}
