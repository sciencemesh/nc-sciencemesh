<?php

namespace OCA\ScienceMesh\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;

use OCA\ScienceMesh\AppConfig;
use OCA\ScienceMesh\Crypt;
use OCA\ScienceMesh\DocumentService;

/**
 * Settings controller for the administration page
 */
class SettingsController extends Controller {
    private $logger;
    private $config;
    private $urlGenerator;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param AppConfig $config - application configuration
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IURLGenerator $urlGenerator,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config
                                    ) {
        parent::__construct($AppName, $request);

        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->config = $config;

	$eventDispatcher = \OC::$server->getEventDispatcher();
	$eventDispatcher->addListener(
	'OCA\Files::loadAdditionalScripts',
	function() {
		\OCP\Util::addScript('sciencemesh', 'settings');
		\OCP\Util::addStyle('sciencemesh', 'style');
	}
);
    }

    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function index() {

        $data = [
            "iop_url" => $this->config->GetConfigValue("iop_url")
        ];
        return new TemplateResponse($this->appName, "settings", $data, "blank");
    }


    /**
     * Get app settings
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function GetSettings() {
        $result = [
            "formats" => $this->config->FormatsSetting(),
            "sameTab" => $this->config->GetSameTab(),
            "shareAttributesVersion" => $this->config->ShareAttributesVersion()
        ];
        return $result;
    }
}
