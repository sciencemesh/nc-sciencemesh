<?php

namespace OCA\ScienceMesh\Controller;

use OCP\IRequest;
use OCP\IUserManager;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\IUserSession;
use OCA\ScienceMesh\RevaHttpClient;
use OCA\ScienceMesh\Plugins\ScienceMeshGenerateTokenPlugin;
use OCA\ScienceMesh\Plugins\ScienceMeshAcceptTokenPlugin;

class AppController extends Controller {
	private $userId;
	private $userManager;
	private $urlGenerator;
	private $config;
	private $userSession;
	private $generateToken;
	private $acceptToken;
	private $httpClient;

	public function __construct($AppName, ITimeFactory $timeFactory, INotificationManager $notificationManager, IRequest $request, IConfig $config, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IUserSession $userSession, ScienceMeshGenerateTokenPlugin $generateToken, ScienceMeshAcceptTokenPlugin $acceptToken, RevaHttpClient $httpClient) {
		parent::__construct($AppName, $request);
			  
		$this->userId = $userId;
		$this->userManager = $userManager;
		$this->request = $request;
		$this->urlGenerator = $urlGenerator;
		$this->notificationManager = $notificationManager;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
		$this->serverConfig = new \OCA\ScienceMesh\ServerConfig($config, $urlGenerator, $userManager);
		$this->userSession = $userSession;
		$this->generateToken = $generateToken;
		$this->acceptToken = $acceptToken;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function launcher() {
		$revaClient = new RevaHttpClient();
		/*
			$revaResult = $revaClient->createShare(array(
				"path" => "/share",
				"recipientUsername" => "marie",
				"recipientHost" => "localhost:17000"
			));
		*/
		// $revaResult = $revaClient->ocmProvider();
		$launcherData = [
			// 	"reva" => json_encode($revaResult, JSON_PRETTY_PRINT)
		];

		$templateResponse = new TemplateResponse('sciencemesh', 'launcher', $launcherData);
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
	public function notifications() {
		$user = $this->userSession->getUser();
		//$user = $this->userManager->get("alice");
		$shortMessage = "ScienceMesh notification!";
		$longMessage = "A longer notification message from ScienceMesh";
		$notification = $this->notificationManager->createNotification();

		$time = $this->timeFactory->getTime();
		$datetime = new \DateTime();
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
				->addAction($declineAction)
						;
			if ($longMessage !== '') {
				$notification->setMessage('remote_share', [$longMessage]);
			}

			$this->notificationManager->notify($notification);
		} catch (\InvalidArgumentException $e) {
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
	public function generate() {
		//$invitationsData = $this->generateToken->getGenerateTokenResponse();
		$invitationsData = [];
		$templateResponse = new TemplateResponse('sciencemesh', 'generate', $invitationsData);
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
	public function accept() {
		//$invitationsData = $this->generateToken->getGenerateTokenResponse();
		$invitationsData = [];
		$templateResponse = new TemplateResponse('sciencemesh', 'accept', $invitationsData);
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
	public function invitationsGenerate() {
		$invitationsData = $this->generateToken->getGenerateTokenResponse($this->userId);
		return new TextPlainResponse($invitationsData, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function contacts() {
		$contactsData = [
		];
		$templateResponse = new TemplateResponse('sciencemesh', 'contacts', $contactsData);
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
	public function contactsAccept() {
		$providerDomain = $this->request->getParam('providerDomain');
		$token = $this->request->getParam('token');
		$contacts = $this->acceptToken->getAcceptTokenResponse($providerDomain, $token, $this->userId);
		return new TextPlainResponse($contacts, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function contactsFindUsers() {
		$find_users = $this->acceptToken->findAcceptedUsers($this->userId);
		
		return new TextPlainResponse($find_users, Http::STATUS_OK);
	}
}
