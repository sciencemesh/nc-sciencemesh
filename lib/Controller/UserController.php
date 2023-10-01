<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\Controller;

use Exception;
use OC\Config;
use OCA\ScienceMesh\ServerConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\IRootFolder;
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

    /**
     * User Controller.
     *
     * @param string $appName
     * @param IRequest $request
     * @param IUserManager $userManager
     * @param IConfig $config
     * @param IL10N $l10n
     * @param ILogger $logger
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
    }

    /**
     * @throws NotPermittedException
     * @throws Exception
     */
    private function checkRevadAuth()
    {
        error_log("checkRevadAuth");
        $authHeader = $this->request->getHeader("X-Reva-Secret");

        if ($authHeader != $this->config->getRevaSharedSecret()) {
            throw new NotPermittedException("Please set an http request header 'X-Reva-Secret: <your_shared_secret>'!");
        }
    }

    // TODO: @Mahdi Move to utils.
    private function formatUser($user): array
    {
        return [
            "id" => [
                "idp" => $this->config->getIopIdp(),
                "opaque_id" => $user->getUID(),
            ],
            "display_name" => $user->getDisplayName(),
            "username" => $user->getUID(),
            "email" => $user->getEmailAddress(),
            "type" => 1,
        ];
    }

    /**
     * get user list.
     *
     * @PublicPage
     * @NoCSRFRequired
     * @NoSameSiteCookieRequired
     * @throws NotPermittedException
     */
    public function getUser($dummy): JSONResponse
    {
        $this->checkRevadAuth();

        $userToCheck = $this->request->getParam("opaque_id");

        if ($this->userManager->userExists($userToCheck)) {
            $user = $this->userManager->get($userToCheck);
            $response = $this->formatUser($user);
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
     */
    public function getUserByClaim($dummy): JSONResponse
    {
        $this->checkRevadAuth();

        $userToCheck = $this->request->getParam("value");

        if ($this->request->getParam("claim") == "username") {
            error_log("GetUserByClaim, claim = 'username', value = $userToCheck");
        } else {
            return new JSONResponse("Please set the claim to username", Http::STATUS_BAD_REQUEST);
        }

        if ($this->userManager->userExists($userToCheck)) {
            $user = $this->userManager->get($userToCheck);
            $response = $this->formatUser($user);
            return new JSONResponse($response, Http::STATUS_OK);
        }

        return new JSONResponse(["message" => "User does not exist"], Http::STATUS_NOT_FOUND);
    }
}
