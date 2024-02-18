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

class UserController extends Controller
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

    /**
     * User Controller.
     *
     * @param string $appName
     * @param IRequest $request
     * @param IConfig $config
     * @param IL10N $l10n
     * @param ILogger $logger
     * @param IUserManager $userManager
     */
    public function __construct(
        string       $appName,
        IRequest     $request,
        IConfig      $config,
        IL10N        $l10n,
        ILogger      $logger,
        IUserManager $userManager
    )
    {
        parent::__construct($appName, $request);
        require_once(__DIR__ . "/../../vendor/autoload.php");

        $this->request = $request;
        $this->config = new ServerConfig($config);
        $this->l = $l10n;
        $this->logger = $logger;
        $this->userManager = $userManager;
        $this->utils = new StaticMethods($l10n, $logger);
    }

    /**
     * get user list.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @NoSameSiteCookieRequired
     * @throws NotPermittedException
     * @throws Exception
     */
    public function getUser($dummy): JSONResponse
    {
        $this->utils->checkRevadAuth($this->request, $this->config->getRevaSharedSecret());

        $userToCheck = $this->request->getParam("opaque_id");

        if ($this->userManager->userExists($userToCheck)) {
            $user = $this->userManager->get($userToCheck);
            $response = $this->utils->formatUser($user, $this->config->getIopIdp());
            return new JSONResponse($response, Http::STATUS_OK);
        }

        return new JSONResponse(["message" => "User does not exist"], Http::STATUS_NOT_FOUND);
    }

    /**
     * get user by claim.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @NoSameSiteCookieRequired
     *
     * @throws NotPermittedException
     * @throws Exception
     */
    public function getUserByClaim($dummy): JSONResponse
    {
        $this->utils->checkRevadAuth($this->request, $this->config->getRevaSharedSecret());

        $userToCheck = $this->request->getParam("value");

        if ($this->request->getParam("claim") == "username") {
            error_log("GetUserByClaim, claim = 'username', value = $userToCheck");
        } else {
            return new JSONResponse("Please set the claim to username", Http::STATUS_BAD_REQUEST);
        }

        if ($this->userManager->userExists($userToCheck)) {
            $user = $this->userManager->get($userToCheck);
            $response = $this->utils->formatUser($user, $this->config->getIopIdp());
            return new JSONResponse($response, Http::STATUS_OK);
        }

        return new JSONResponse(["message" => "User does not exist"], Http::STATUS_NOT_FOUND);
    }
}
