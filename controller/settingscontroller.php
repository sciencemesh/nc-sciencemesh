<?php

namespace OCA\ScienceMesh\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCA\ScienceMesh\AppConfig;
use OCA\ScienceMesh\Crypt;
use OCA\ScienceMesh\DocumentService;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

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
	$data = $this->loadSettings();
	if (!$data) {
		// settings has not been set
		$hostname = \OCP\Util::getServerHostName();
		$data = ["hostname" => $hostname];
		$data["iopurl"] = "";
		$data["country"] = "";
		$data["sitename"] = "";
		$data["siteurl"] = "";
	}


        return new TemplateResponse($this->appName, "settings", $data, "blank");
    }

	/**
	 * Simply method that posts back the payload of the request
	 * @NoAdminRequired
	 */
	public function saveSettings($iopurl, $country, $hostname, $sitename, $siteurl) {
		// store settings in DB
		$this->deleteSettings();
		$ok = $this->storeSettings($iopurl, $country, $hostname, $sitename, $siteurl);
		if (!$ok) {
			return new DataResponse([
				'error' => 'error storing settings, check server logs'
			]);
		}

		return new DataResponse([
			'iopurl' => $iopurl,
			'country' => $country,
			'hostname' => $hostname,
			'sitename' => $sitename,
			'siteurl' => $siteurl
		]);
	}

	private function storeSettings($iopurl, $country, $hostname, $sitename, $siteurl){
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->insert('sciencemesh')
			->setValue('iopurl', $query->createNamedParameter($iopurl))
			->setValue('country', $query->createNamedParameter($country))
			->setValue('sitename', $query->createNamedParameter($sitename))
			->setValue('siteurl', $query->createNamedParameter($siteurl))
			->setValue('hostname', $query->createNamedParameter($hostname));
		$result = $query->execute();
		if (!$result) {
			\OC::$server->getLogger()->error('sciencemesh database cound not be updated', 
				['app' => 'sciencemesh']);
			return false;
		}
		return true;
	}

	private function deleteSettings(){
		$deleteQuery = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$deleteQuery->delete('sciencemesh');
		$deleteQuery->execute();
	}

	private function loadSettings(){
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->select('*')->from('sciencemesh');
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();
		return $row;
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
