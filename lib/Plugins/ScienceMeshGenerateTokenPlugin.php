<?php

namespace OCA\ScienceMesh\Plugins;

use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\ScienceMesh\RevaHttpClient;

class ScienceMeshGenerateTokenPlugin {
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
		if ($user !== null) {
			$this->userId = $user->getUID();
		}
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->httpClient = $httpClient;
	}

	public function generateTokenFromReva() {
		$request = [
			'opaque' => [
				'map' => [
					'key' => 'test123',
					'value' => 'test123'
				]
			]
		];
		$tokenFromReva = $this->httpClient->revaPost('ocm-invite-generate', json_encode($request)); //params will be empty or not fix me
		return $tokenFromReva;
	}
}
