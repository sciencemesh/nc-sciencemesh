<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Michiel De Jong <michiel@pondersource.com>
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\Controller;

use Exception;
use OC;
use OCA\ScienceMesh\RevaHttpClient;
use OCA\ScienceMesh\ServerConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Util;

/**
 * Settings controller for the administration page
 */
class SettingsController extends Controller
{
    const CATALOG_URL = "https://iop.sciencemesh.uni-muenster.de/iop/mentix/sitereg";

    /** @var string */
    private string $userId;

    /** @var ServerConfig */
    private ServerConfig $serverConfig;

    /** @var IConfig */
    private IConfig $sciencemeshConfig;

    /**
     * @param string $appName - application name
     * @param IRequest $request - request object
     */
    public function __construct(
        string   $appName,
        string   $userId,
        IRequest $request,
        IConfig  $sciencemeshConfig
    )
    {
        parent::__construct($appName, $request);

        $this->userId = $userId;
        $this->serverConfig = new ServerConfig($sciencemeshConfig);
        $this->sciencemeshConfig = $sciencemeshConfig;

        $eventDispatcher = OC::$server->getEventDispatcher();
        $eventDispatcher->addListener(
            'OCA\Files::loadAdditionalScripts',
            function () {
                Util::addScript('sciencemesh', 'settings');
                Util::addStyle('sciencemesh', 'style');
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
    public function index(): TemplateResponse
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

    private function loadSettings()
    {
        $query = OC::$server->getDatabaseConnection()->getQueryBuilder();
        $query->select('*')->from('sciencemesh');
        $result = $query->execute();
        $row = $result->fetch();
        $result->closeCursor();
        return $row;
    }

    /**
     * Simply method that posts back the payload of the request
     * @NoAdminRequired
     */
    public function saveSettings($apikey, $sitename, $siteurl, $country, $iopurl, $numusers, $numfiles, $numstorage): DataResponse
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
            } catch (Exception $e) {
                return new DataResponse([
                    'error' => $e->getMessage()
                ]);
            }
        }

        // store settings in DB
        $this->deleteSettings();
        try {
            $this->storeSettings($apikey, $sitename, $siteurl, $siteid, $country, $iopurl, $numusers, $numfiles, $numstorage);
        } catch (Exception $e) {
            return new DataResponse([
                'error' => 'error storing settings: ' . $e->getMessage()
            ]);
        }

        return new DataResponse(["siteid" => $siteid]);
    }

    /**
     * @throws Exception
     */
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
            throw new Exception($respData["error"]);
        }
    }

    private function deleteSettings()
    {
        $deleteQuery = OC::$server->getDatabaseConnection()->getQueryBuilder();
        $deleteQuery->delete('sciencemesh');
        $deleteQuery->execute();
    }

    /**
     * @throws Exception
     */
    private function storeSettings($apikey, $sitename, $siteurl, $siteid, $country, $iopurl, $numusers, $numfiles, $numstorage)
    {
        $query = OC::$server->getDatabaseConnection()->getQueryBuilder();
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
            OC::$server->getLogger()->error('sciencemesh database could not be updated', ['app' => 'sciencemesh']);
            throw new Exception('sciencemesh database could not be updated');
        }
    }

    /**
     * Save sciencemesh settings
     *
     * @return DataResponse
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function saveSciencemeshSettings(): DataResponse
    {
        $sciencemesh_iop_url = $this->request->getParam('sciencemesh_iop_url');
        $sciencemesh_shared_secret = $this->request->getParam('sciencemesh_shared_secret');

        $this->serverConfig->setIopUrl($sciencemesh_iop_url);
        $this->serverConfig->setRevaSharedSecret($sciencemesh_shared_secret);

        return new DataResponse(["status" => true]);
    }

    /**
     * Check IOP URL connection
     *
     * @return DataResponse
     *
     * @NoAdminRequired
     * @PublicPage
     * @throws Exception
     */

    public function checkConnectionSettings(): DataResponse
    {
        $revaHttpClient = new RevaHttpClient($this->sciencemeshConfig);
        $response_sciencemesh_iop_url = $revaHttpClient->ocmProvider($this->userId);
        return new DataResponse($response_sciencemesh_iop_url);
    }
}
