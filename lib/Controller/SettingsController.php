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
class SettingsController extends Controller
{
	private $logger;
	private $config;
	private $urlGenerator;

	const CATALOG_URL = "https://iop.sciencemesh.uni-muenster.de/iop/mentix/sitereg";

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
	)
	{
		parent::__construct($AppName, $request);

		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->config = $config;

		$eventDispatcher = \OC::$server->getEventDispatcher();
		$eventDispatcher->addListener(
			'OCA\Files::loadAdditionalScripts',
			function () {
				\OCP\Util::addScript('sciencemesh', 'settings');
				\OCP\Util::addStyle('sciencemesh', 'style');
			}
		);
	}

	/**
	 * Print config section
	 * FIXME: https://github.com/pondersource/nc-sciencemesh/issues/215
	 * Listing is OK, but changing these settings
	 * should probably really require Nextcloud server admin permissions!
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return TemplateResponse
	 */
	public function index()
	{
		$data = $this->loadSettings();
		if (!$data) {
			// settings has not been set
			$data = [
				"apikey" => "",
				"sitename" => "",
				"siteurl" => "",
				"siteid" => "",
				"country" => "",
				"iopurl" => "",
				"numusers" => 0,
				"numfiles" => 0,
				"numstorage" => 0
			];
		}
		return new TemplateResponse($this->appName, "settings", $data, "blank");
	}

	/**
	 * Simply method that posts back the payload of the request
	 * @NoAdminRequired
	 */
	public function saveSettings($apikey, $sitename, $siteurl, $country, $iopurl, $numusers, $numfiles, $numstorage)
	{
		$siteid = null;

		if ($numusers == null) {
			$numusers = 0;
		}
		if ($numfiles == null) {
			$numfiles = 0;
		}
		if ($numstorage == null) {
			$numstorage = 0;
		}

		// submit settings to Mentix (if they are valid)
		if ($apikey !== "" && $sitename !== "" && $siteurl !== "" && $iopurl !== "") {
			try {
				$siteid = $this->submitSettings($apikey, $sitename, $siteurl, $country, $iopurl);
			} catch (\Exception $e) {
				return new DataResponse([
					'error' => $e->getMessage()
				]);
			}
		}

		// store settings in DB
		$this->deleteSettings();
		try {
			$this->storeSettings($apikey, $sitename, $siteurl, $siteid, $country, $iopurl, $numusers, $numfiles, $numstorage);
		} catch (\Exception $e) {
			return new DataResponse([
				'error' => 'error storing settings: ' . $e->getMessage()
			]);
		}

		return new DataResponse(["siteid" => $siteid]);
	}

	private function storeSettings($apikey, $sitename, $siteurl, $siteid, $country, $iopurl, $numusers, $numfiles, $numstorage)
	{
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->insert('sciencemesh')
			->setValue('apikey', $query->createNamedParameter($apikey))
			->setValue('sitename', $query->createNamedParameter($sitename))
			->setValue('siteurl', $query->createNamedParameter($siteurl))
			->setValue('siteid', $query->createNamedParameter($siteid))
			->setValue('country', $query->createNamedParameter($country))
			->setValue('iopurl', $query->createNamedParameter($iopurl))
			->setValue('numusers', $query->createNamedParameter($numusers))
			->setValue('numfiles', $query->createNamedParameter($numfiles))
			->setValue('numstorage', $query->createNamedParameter($numstorage));
		$result = $query->execute();

		if (!$result) {
			\OC::$server->getLogger()->error('sciencemesh database cound not be updated', ['app' => 'sciencemesh']);
			throw new \Exception('sciencemesh database cound not be updated');
		}
	}

	private function deleteSettings()
	{
		$deleteQuery = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$deleteQuery->delete('sciencemesh');
		$deleteQuery->execute();
	}

	private function loadSettings()
	{
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->select('*')->from('sciencemesh');
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();
		return $row;
	}

	private function submitSettings($apikey, $sitename, $siteurl, $country, $iopurl)
	{
		// fill out a data object as needed by Mentix
		$iopPath = parse_url($iopurl, PHP_URL_PATH);
		$data = json_encode([
			"name" => $sitename,
			"url" => $siteurl,
			"countryCode" => $country,
			"reva" => [
				"url" => $iopurl,
				"metricsPath" => rtrim($iopPath, "/") . "/metrics"
			]
		]);
		$url = self::CATALOG_URL . "?action=register&apiKey=" . urlencode($apikey);

		// use CURL to send the request to Mentix
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		$response = curl_exec($curl);
		$respData = json_decode($response, true);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($status == 200) {
			return $respData["id"];
		} else {
			throw new \Exception($respData["error"]);
		}
	}

	/**
	 * Get app settings
	 *
	 * @return array
	 *
	 * @NoAdminRequired
	 * @PublicPage
	 */
	public function GetSettings()
	{
		$result = [
			"formats" => $this->config->FormatsSetting(),
			"sameTab" => $this->config->GetSameTab(),
			"shareAttributesVersion" => $this->config->ShareAttributesVersion()
		];
		return $result;
	}
}
