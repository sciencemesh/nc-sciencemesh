<?php
/**
 * ownCloud - ScienceMesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright ScienceMesh 2020 - 2024
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.io>
 */

namespace OCA\ScienceMesh\Controller;


use Exception;
use OC\Config;
use OCA\ScienceMesh\ServerConfig;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCA\ScienceMesh\Utils\StaticMethods;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

use OCP\IUserManager;

use OCP\Share\Exceptions\IllegalIDChangeException;
use OCP\Share\Exceptions\ShareNotFound;

class AuthController extends Controller
{
    /** @var Config */
    private $config;

    /** @var IL10N */
    private IL10N $l;

    /** @var ILogger */
    private ILogger $logger;

    /** @var IUserManager */
    private IUserManager $userManager;

    /** @var StaticMethods */
    private StaticMethods $utils;

    /** @var ScienceMeshShareProvider */
    private ScienceMeshShareProvider $shareProvider;


    /**
     * Authentication Controller.
     *
     * @param string $appName
     * @param IRequest $request
     * @param IConfig $config
     * @param IL10N $l10n
     * @param ILogger $logger
     * @param IUserManager $userManager
     * @param ScienceMeshShareProvider $shareProvider
     */
    public function __construct(
        string                   $appName,
        IRequest                 $request,

        IConfig                  $config,
        IL10N                    $l10n,
        ILogger                  $logger,
        IUserManager             $userManager,
        ScienceMeshShareProvider $shareProvider
    )
    {
        parent::__construct($appName, $request);
        require_once(__DIR__ . "/../../vendor/autoload.php");

        $this->request = $request;
        $this->config = new ServerConfig($config);
        $this->l = $l10n;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->shareProvider = $shareProvider;
        $this->utils = new StaticMethods($l10n, $logger);
    }

    /**
     * @PublicPage
     * @NoAdminRequired
     * @NoCSRFRequired
     * @param $userId
     * @return JSONResponse
     * @throws NotPermittedException
     * @throws ShareNotFound|IllegalIDChangeException
     * @throws Exception
     */
    public function Authenticate($userId): JSONResponse
    {
        error_log("Authenticate: " . $userId);

        $this->utils->checkRevadAuth($this->request, $this->config->getRevaSharedSecret());

        if ($this->userManager->userExists($userId)) {
            $userId = $this->request->getParam("clientID");
            $password = $this->request->getParam("clientSecret");
            // Try e.g.:
            // curl -v -H 'Content-Type:application/json' -d'{"clientID":"einstein",clientSecret":"relativity"}' http://einstein:relativity@localhost/index.php/apps/sciencemesh/~einstein/api/auth/Authenticate

            // see: https://github.com/cs3org/reva/issues/2356
            if ($password == $this->config->getRevaLoopbackSecret()) {
                // NOTE: @Mahdi, usually everything goes in this branch.
                $user = $this->userManager->get($userId);
            } else {
                $user = $this->userManager->checkPassword($userId, $password);
            }

        } else {
            $share = $this->shareProvider->getSentShareByToken($userId);
            $userId = $share->getSharedBy();
            $user = $this->userManager->get($userId);
        }

        if ($user) {
            // FIXME: @Mahdi this hardcoded value represents the json below and is not needed.
            // {
            //  "resource_id": {
            //    "storage_id": "storage-id",
            //    "opaque_id": "opaque-id"
            //  },
            //  "path": "some/file/path.txt"
            // }
            $result = [
                "user" => $this->utils->formatUser($user, $this->config->getIopIdp()),
                "scopes" => [
                    "user" => [
                        "resource" => [
                            "decoder" => "json",
                            "value" => "eyJyZXNvdXJjZV9pZCI6eyJzdG9yYWdlX2lkIjoic3RvcmFnZS1pZCIsIm9wYXF1ZV9pZCI6Im9wYXF1ZS1pZCJ9LCJwYXRoIjoic29tZS9maWxlL3BhdGgudHh0In0=",
                        ],
                        "role" => 1,
                    ],
                ],
            ];

            return new JSONResponse($result, Http::STATUS_OK);
        }

        return new JSONResponse("Username / password not recognized", Http::STATUS_UNAUTHORIZED);
    }
}
