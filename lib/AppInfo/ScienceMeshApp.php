<?php

namespace OCA\ScienceMesh\AppInfo;

use OCP\AppFramework\App;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;


class ScienceMeshApp extends App {
	public const APP_ID = 'sciencemesh';
	public const SHARE_TYPE_REMOTE = 6;
	
	public function __construct() {
		parent::__construct(self::APP_ID);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService('UserService', function ($c) {
			return new \OCA\ScienceMesh\Service\UserService(
				$c->query('UserSession')
			);
		});
		$container->registerService('UserSession', function ($c) {
			return $c->query('ServerContainer')->getUserSession();
		});

		// currently logged in user, userId can be gotten by calling the
		// getUID() method on it
		$container->registerService('User', function ($c) {
			return $c->query('UserSession')->getUser();
		});




		$notificationManager = $server->getNotificationManager();
        $notificationManager->registerNotifier(function () use ($notificationManager) {
            return $this->getContainer()->query('\OCA\ScienceMesh\Notifier\ScienceMeshNotifier');
        }, function () {
            $l = \OC::$server->getL10N('sciencemesh');
            return [
                'id' => 'sciencemesh',
                'name' => $l->t('Science Mesh'),
            ];
        });
	}

    /**
     * @return ScienceMeshShareProvider
     */
    public function getScienceMeshShareProvider()
    {
        $container = $this->getContainer();
        $dbConnection = $container->query("OCP\IDBConnection");
        $i10n = $container->query("OCP\IL10N");

        $logger = $container->query("OCP\ILogger");
        $rootFolder = $container->query("OCP\Files\IRootFolder");

        $config = $container->query("OCP\IConfig");
        $userManager = $container->query("OCP\IUserManger");
        $gsConfig = new GlobalScaleConfig($config);

        return new ScienceMeshShareProvider($dbConnection, $i10n, $logger, $rootFolder, $config, $userManager, $gsConfig);
    }
}
