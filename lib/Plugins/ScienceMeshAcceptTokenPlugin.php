<?php

namespace OCA\ScienceMesh\Plugins;

use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\ScienceMesh\RevaHttpClient;
use OCP\IRequest;

class ScienceMeshAcceptTokenPlugin {
	protected $shareeEnumeration;
	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var string */
	private $userId = '';
	private $httpClient;
	private $request;

	public function __construct(IConfig $config, IUserManager $userManager, IUserSession $userSession, RevaHttpClient $httpClient, IRequest $request) {
		$this->config = $config;
		$this->userManager = $userManager;
		$user = $userSession->getUser();
	
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->httpClient = $httpClient;
		$this->request = $request;
	}

	public function getAcceptTokenResponse() {
		$invitationsData = $this->getAcceptTokenFromReva();
		
		return $invitationsData;
	}
	public function findAcceptedUsers() {
		$users = $this->httpClient->revaPost('invites/find-accepted-users');
		return $users;
	}

	public function getAcceptTokenFromReva() {
		$request = [
			'providerDomain' => $this->request->getParam("providerDomain"),
			'token' => $this->request->getParam("token")
		];
		$tokenFromReva = $this->httpClient->revaPost('invites/forward', http_build_query($request)); //params will be empty or not fix me
		return $tokenFromReva;
	}
}
