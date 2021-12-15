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

	public function getAcceptTokenResponse($providerDomain, $token, $userId) {
		return $this->httpClient->getAcceptTokenFromReva($providerDomain, $token, $userId);
	}

	public function findAcceptedUsers($userId) {
	  return $this->httpClient->findAcceptedUsers($userId);
	}
}
