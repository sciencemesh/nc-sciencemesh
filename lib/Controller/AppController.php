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

namespace OCA\ScienceMesh\Controller;

use DateTime;
use Exception;
use InvalidArgumentException;
use OCA\ScienceMesh\PlainResponse;
use OCA\ScienceMesh\RevaHttpClient;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Mail\IMailerException;
use OCP\Mail\IMessage;
use OCP\Notification\IManager as INotificationManager;

class AppController extends Controller
{
    /** @var ?string */
    private ?string $userId;

    /** @var IConfig */
    private IConfig $config;

    /** @var IMailer */
    private IMailer $mailer;

    /** @var ITimeFactory */
    private ITimeFactory $timeFactory;

    /** @var IUserSession */
    private IUserSession $userSession;

    /** @var RevaHttpClient */
    private RevaHttpClient $revaHttpClient;

    /** @var INotificationManager */
    private INotificationManager $notificationManager;

    public function __construct(
        string               $appName,
        ?string              $userId,
        IConfig              $config,
        IMailer              $mailer,
        IRequest             $request,
        ITimeFactory         $timeFactory,
        IUserSession         $userSession,
        RevaHttpClient       $revaHttpClient,
        INotificationManager $notificationManager
    )
    {
        parent::__construct($appName, $request);

        $this->userId = $userId;
        $this->config = $config;
        $this->mailer = $mailer;
        $this->timeFactory = $timeFactory;
        $this->userSession = $userSession;
        $this->revaHttpClient = $revaHttpClient;
        $this->notificationManager = $notificationManager;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function notifications()
    {
        $user = $this->userSession->getUser();
        $shortMessage = "ScienceMesh notification!";
        $notification = $this->notificationManager->createNotification();

        $time = $this->timeFactory->getTime();
        $datetime = new DateTime();
        $datetime->setTimestamp($time);

        try {
            $acceptAction = $notification->createAction();
            $acceptAction->setLabel('accept')->setLink("shared", "GET");

            $declineAction = $notification->createAction();
            $declineAction->setLabel('decline')->setLink("shared", "GET");

            $notification->setApp('sciencemesh')
                ->setUser($user->getUID())
                ->setDateTime($datetime)
                ->setObject('sciencemesh', dechex($time))
                ->setSubject('remote_share', [$shortMessage])
                ->addAction($acceptAction)
                ->addAction($declineAction);

            $this->notificationManager->notify($notification);
        } catch (InvalidArgumentException $e) {
            return new DataResponse(null, Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $notificationsData = [
        ];
        $templateResponse = new TemplateResponse('sciencemesh', 'notifications', $notificationsData);
        $policy = new ContentSecurityPolicy();
        $policy->addAllowedStyleDomain("data:");
        $policy->addAllowedScriptDomain("'self'");
        $policy->addAllowedScriptDomain("'unsafe-inline'");
        $policy->addAllowedScriptDomain("'unsafe-eval'");
        $templateResponse->setContentSecurityPolicy($policy);
        return $templateResponse;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function generate(): TemplateResponse
    {
        return new TemplateResponse('sciencemesh', 'generate');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function accept(): TemplateResponse
    {
        return new TemplateResponse('sciencemesh', 'accept');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function invitationsGenerate(): PlainResponse
    {
        $recipient = $this->request->getParam('email');
        $invitationsData = $this->revaHttpClient->generateTokenFromReva($this->userId, $recipient);

        // check if invite_link exist before accessing.
        $inviteLinkStr = $invitationsData["invite_link"] ?? false;

        if (!$inviteLinkStr) {
            return new PlainResponse("Unexpected response from Reva", Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new PlainResponse("$inviteLinkStr", Http::STATUS_OK);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function invitationsSends($AppName): PlainResponse
    {
        $email = $this->request->getParam('email');
        $token = $this->request->getParam('token');

        $recipientEmail = $email;
        $subject = 'New Token generated by ' . $AppName . ' send from ' . $this->userId;
        $message = 'You can open this URL to accept the invitation<br>' . $token;

        $mailer = $this->sendNotification($recipientEmail, $subject, $message);
        return new PlainResponse($mailer, Http::STATUS_OK);
    }

    public function sendNotification(string $recipient, string $subject, string $message): bool
    {
        try {
            // Create a new email message
            $mail = $this->mailer->createMessage();

            $mail->setTo([$recipient]);
            $mail->setSubject($subject);
            $mail->setPlainBody($message);

            // Set the "from" email address
            $fromEmail = $this->config->getSystemValue('fromemail', 'no-reply@cs3mesh4eosc.eu');
            $mail->setFrom([$fromEmail]);

            // Send the email
            $this->mailer->send($mail);

            return true;
        } catch (IMailerException $e) {
            error_log(json_encode($e));

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contacts(): TemplateResponse
    {
        $contactsData = [
        ];
        return new TemplateResponse('sciencemesh', 'contacts', $contactsData);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contactsAccept(): PlainResponse
    {
        $providerDomain = $this->request->getParam('providerDomain');
        $token = $this->request->getParam('token');
        $result = $this->revaHttpClient->acceptInvite($providerDomain, $token, $this->userId);
        return new PlainResponse($result, Http::STATUS_OK);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contactsFindUsers($searchToken = ""): PlainResponse
    {
        $find_users_json = $this->revaHttpClient->findAcceptedUsers($this->userId);

        $find_users = json_decode($find_users_json, false);
        $return_users = array();
        if (strlen($searchToken) > 0) {
            if (!empty($find_users)) {
                for ($i = count($find_users); $i >= 0; $i--) {
                    if (str_contains($find_users[$i]->display_name, $searchToken) and !is_null($find_users[$i])) {
                        $return_users[] = $find_users[$i];
                    }
                }
            }
        } else {
            $return_users = json_decode($find_users_json, false);
        }

        error_log('test:' . json_encode($return_users));
        return new PlainResponse(json_encode($return_users), Http::STATUS_OK);
    }
}
