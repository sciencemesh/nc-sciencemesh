<?php

namespace OCA\ScienceMesh;

use OCA\ScienceMesh\ServerConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Settings implements ISettings {
	private $config;

	public function __construct(Serverconfig $config) {
		$this->config = $config;
	}

	public function getForm() {
		$response = new TemplateResponse('sciencemesh', 'settings-admin');
		$response->setParams([
			'apiKey' => $this->config->getApiKey(),
			'siteName' => $this->config->getSiteName(),
			'siteUrl' => $this->config->getSiteUrl(),
			'siteId' => $this->config->getSiteId(),
			'country' => $this->config->getCountry(),
			'iopUrl' => $this->config->getIopUrl(),
			'numUsers' => $this->config->getNumUsers(),
			'numFiles' => $this->config->getNumFiles(),
			'numStorage' => $this->config->getNumStorage()
		]);
		return $response;
	}

	public function getSection() {
		return 'sharing';
	}

	public function getPriority() {
		return 50;
	}
}