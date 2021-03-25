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

	const CATALOG_URL = "https://sciencemesh-test.uni-muenster.de/api/mentix/sitereg";

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
	 *
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
		// store settings in DB
		$this->deleteSettings();
		$ok = $this->storeSettings($apikey, $sitename, $siteurl, $country, $iopurl, $numusers, $numfiles, $numstorage);
		if (!$ok) {
			return new DataResponse([
				'error' => 'error storing settings, check server logs'
			]);
		}

		// submit settings to Mentix (if they are valid)
		if ($apikey !== "" && $sitename !== "" && $siteurl !== "" && $iopurl !== "") {
			$err = $this->submitSettings($apikey, $sitename, $siteurl, $country, $iopurl);
			if ($err != null) {
				return new DataResponse([
					'error' => $err
				]);
			}
		}

		return new DataResponse([]);
	}

	private function storeSettings($apikey, $sitename, $siteurl, $country, $iopurl, $numusers, $numfiles, $numstorage)
	{
		$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$query->insert('sciencemesh')
			->setValue('apikey', $query->createNamedParameter($apikey))
			->setValue('sitename', $query->createNamedParameter($sitename))
			->setValue('siteurl', $query->createNamedParameter($siteurl))
			->setValue('country', $query->createNamedParameter($country))
			->setValue('iopurl', $query->createNamedParameter($iopurl))
			->setValue('numusers', $query->createNamedParameter($numusers))
			->setValue('numfiles', $query->createNamedParameter($numfiles))
			->setValue('numstorage', $query->createNamedParameter($numstorage));
		$result = $query->execute();

		if (!$result) {
			\OC::$server->getLogger()->error('sciencemesh database cound not be updated',
				['app' => 'sciencemesh']);
			return false;
		}
		return true;
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
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($status != 200) {
			try {
				$respData = json_decode($response, true);
				return $respData["error"];
			} catch (\Exception $e) {
				return $e->getMessage();
			}
		}

		return null;
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
