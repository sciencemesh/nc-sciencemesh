<?php

namespace OCA\ScienceMesh\Plugins;

use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\ScienceMesh\RevaHttpClient;

class ScienceMeshAcceptTokenPlugin {
	protected $shareeEnumeration;
	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var string */
	private $userId = '';
	private $httpClient;

	public function __construct(IConfig $config, IUserManager $userManager, IUserSession $userSession, RevaHttpClient $httpClient) {
		$this->config = $config;
		$this->userManager = $userManager;
		$user = $userSession->getUser();
	
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->httpClient = $httpClient;
	}

	public function getAcceptTokenResponse() {
		$invitationsData = $this->getAcceptTokenFromReva();
		
		return $invitationsData;
	}

	public function getAcceptTokenFromReva() {
		$request = [
			'idp' => 'https://cernbox.cern.ch',
			'token' => 'dbc08800-553b-45d4-ad02-b542199648ab'
		];
		$tokenFromReva = $this->httpClient->revaPost('invites/forward', json_encode($request)); //params will be empty or not fix me
		return $tokenFromReva;
	}
}
