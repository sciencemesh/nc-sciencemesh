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

	public function __construct($AppName, ITimeFactory $timeFactory, INotificationManager $notificationManager, IRequest $request, IConfig $config, IUserManager $userManager, IURLGenerator $urlGenerator, $userId, IUserSession $userSession, RevaHttpClient $httpClient) {
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
		$this->httpClient = $httpClient;
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
		return new TemplateResponse('sciencemesh', 'generate');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function accept() {
		return new TemplateResponse('sciencemesh', 'accept');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function invitationsGenerate() {
		$invitationsData = $this->httpClient->generateTokenFromReva($this->userId);
		$inviteLinkStr = $invitationsData["invite_link"];
		$meshDirectoryUrl = $this->config->getAppValue('sciencemesh', 'meshDirectoryUrl', 'https://sciencemesh.cesnet.cz/iop/meshdir/');
    if (!$inviteLinkStr) {
			return new TextPlainResponse("Unexpected response from Reva", Http::STATUS_INTERNAL_SERVER_ERROR);
		}
    if (!$meshDirectoryUrl) {
			return new TextPlainResponse("Unexpected mesh directory URL configuration", Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new TextPlainResponse("$meshDirectoryUrl$inviteLinkStr", Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function contacts() {
		$contactsData = [
		];
		return new TemplateResponse('sciencemesh', 'contacts', $contactsData);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function contactsAccept() {
		$providerDomain = $this->request->getParam('providerDomain');
		$token = $this->request->getParam('token');
		$result = $this->httpClient->acceptInvite($providerDomain, $token, $this->userId);
		return new TextPlainResponse($result, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function contactsFindUsers($searchToken = "") {
		$find_users_json = $this->httpClient->findAcceptedUsers($this->userId);

		$find_users = json_decode($find_users_json, false);
		
		if(strlen($searchToken) > 0){
			for($i = count($find_users->accepted_users); $i >= 0 ; $i--){
				if(!str_contains($find_users->accepted_users[$i]->display_name, $searchToken)){
					array_splice($find_users->accepted_users, $i, 1);
				}
			}
		}
		
		return new TextPlainResponse(json_encode($find_users), Http::STATUS_OK);
	}



}
