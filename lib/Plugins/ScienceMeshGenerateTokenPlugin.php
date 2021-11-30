<?php

namespace OCA\ScienceMesh\Plugins;

use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\ScienceMesh\RevaHttpClient;
use OCP\Security\ISecureRandom;
use OCA\ScienceMesh\User\ScienceMeshUserId;

class ScienceMeshGenerateTokenPlugin {
	protected $shareeEnumeration;
	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var string */
	private $userId = '';
	private $httpClient;
	private $secureRandom;

	public function __construct(IConfig $config, IUserManager $userManager, IUserSession $userSession, RevaHttpClient $httpClient, ISecureRandom $secureRandom) {
		$this->config = $config;
		$this->userManager = $userManager;
		$user = $userSession->getUser();
		if ($user !== null) {
			$this->userId = $user->getUID();
		}
		$this->shareeEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$this->httpClient = $httpClient;
		$this->secureRandom = $secureRandom;
	}

	public function getGenerateTokenResponse() {
		//$result = $this->generateTokenFromReva();// Configure if the reva endpoint will be ready
		if ($this->userManager->userExists($this->userId)) {
			$invitationsData = [
				"status" => [
					"code" => 1,
					"trace" => "00000000000000000000000000000000"
				],
				"invite_token" => [
					"token" => "161405dd-05f0-4806-8b42-1482b3185c63",
					"user_id" => [
						"idp" => "some-idp",
						"opaque_id" => $this->userId,
						"type" => ScienceMeshUserId::USER_TYPE_PRIMARY
					],
					"expiration" => [
						"seconds" => gmdate("H:i:s", 1638025012)
					]
				]
			];
			return $invitationsData;
		}
		
		return new JSONResponse("Username / password not recognized", 401);
	}

	public function generateTokenFromReva() {
		$request = [
			'opaque' => [
				'map' => [
					'key' => 'test123',
					'value' => [
						'decoder' => 'json',
						'eyJyZXNvdXJjZV9pZCI6eyJzdG9yYWdlX2lkIjoic3RvcmFnZS1pZCIsIm9wYXF1ZV9pZCI6Im9wYXF1ZS1pZCJ9LCJwYXRoIjoic29tZS9maWxlL3BhdGgudHh0In0='
					]
				]
			]
		];
		$tokenFromReva = $this->httpClient->revaPost('invites/generate', json_encode($request)); //params will be empty or not fix me
		return $tokenFromReva;
	}
}
