<?php
namespace OCA\ScienceMesh\Controller;

use OCA\ScienceMesh\ServerConfig;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Contacts\IManager;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;

class AppController extends Controller {
        private $userId;
        private $userManager;
        private $urlGenerator;
        private $config;

        public function __construct($AppName, IRequest $request, IConfig $config, IUserManager $userManager, IURLGenerator $urlGenerator, $userId){
                parent::__construct($AppName, $request);
                $this->userId = $userId;
                $this->userManager = $userManager;
                $this->request     = $request;
                $this->urlGenerator = $urlGenerator;
                $this->config = new \OCA\ScienceMesh\ServerConfig($config, $urlGenerator, $userManager);
        }

        /**
         * @NoAdminRequired
         * @NoCSRFRequired
         */
        public function appLauncher() {
                $appLauncherData = array(
                );
                $templateResponse = new TemplateResponse('sciencemesh', 'applauncher', $appLauncherData);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedStyleDomain("data:");
		$policy->addAllowedScriptDomain("'self'");
		$policy->addAllowedScriptDomain("'unsafe-inline'");
		$policy->addAllowedScriptDomain("'unsafe-eval'");
                $templateResponse->setContentSecurityPolicy($policy);
                return $templateResponse;
        }
}