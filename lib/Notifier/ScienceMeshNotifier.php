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

namespace OCA\ScienceMesh\Notifier;

use InvalidArgumentException;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class ScienceMeshNotifier implements INotifier
{

    /** @var IURLGenerator */
    protected IURLGenerator $urlGenerator;

    /**
     * @param IURLGenerator $urlGenerator
     */
    public function __construct(IURLGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Identifier of the notifier, only use [a-z0-9_]
     *
     * @return string
     * @since 17.0.0
     */
    public function getID(): string
    {
        return 'sciencemesh';
    }

    /**
     * Human-readable name describing the notifier
     *
     * @return string
     * @since 17.0.0
     */
    public function getName(): string
    {
        return "sciencemesh";
    }

    /**
     * @param INotification $notification
     * @param string $languageCode The code of the language that should be used to prepare the notification
     * @return INotification
     * @throws InvalidArgumentException When the notification was not prepared by a notifier
     */

    public function prepare(INotification $notification, $languageCode): INotification
    {
        if ($notification->getApp() !== 'sciencemesh') {
            throw new InvalidArgumentException('Unknown app');
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
                $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('notifications', 'notifications-dark.svg')));

                // Deal with the actions for a known subject
                foreach ($notification->getActions() as $action) {
                    switch ($action->getLabel()) {
                        case 'accept':
                            $action->setParsedLabel("Accept")
                                ->setLink($this->urlGenerator->linkToRouteAbsolute('sciencemesh.app.shared'), 'GET');
                            break;
                        case 'decline':
                            $action->setParsedLabel("Decline")
                                ->setLink($this->urlGenerator->linkToRouteAbsolute('sciencemesh.app.shared'), 'GET');
                            break;
                    }

                    $notification->addParsedAction($action);
                }

                return $notification;

            default:
                throw new InvalidArgumentException('Unknown subject');
        }
    }
}
