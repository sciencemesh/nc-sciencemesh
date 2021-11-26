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

	public function getGenerateTokenResponse() {
		//$result = $this->generateTokenFromReva();// Configure if the reva endpoint will be ready

		$invitationsData = [
			"invite_token" => "4d6196c0-5a59-4db5-bf5a-8e41991051f8",
			"user_id" => "cernbox.cern.ch",
			"opaque_id" => "4c510ada-c86b-4815-8820-42cdf82c3d51" ,
			"type" => 1
		];

		return $invitationsData;
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
