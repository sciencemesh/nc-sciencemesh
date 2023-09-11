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

namespace OCA\ScienceMesh\AppInfo;

use OC;
use OCA\ScienceMesh\GlobalConfig\GlobalScaleConfig;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\QueryException;

class ScienceMeshApp extends App
{
    public const APP_ID = 'sciencemesh';
    public const SCIENCEMESH_POSTFIX = ' (Sciencemesh)';
    public const SHARE_TYPE_REMOTE = 6;
    public const SHARE_TYPE_SCIENCEMESH = 6;

    public function __construct()
    {
        parent::__construct(self::APP_ID);

        $container = $this->getContainer();
        $server = $container->getServer();

        $notificationManager = $server->getNotificationManager();
        $notificationManager->registerNotifier(function () use ($notificationManager) {
            return $this->getContainer()->query('\OCA\ScienceMesh\Notifier\ScienceMeshNotifier');
        }, function () {
            $l = OC::$server->getL10N('sciencemesh');
            return [
                'id' => 'sciencemesh',
                'name' => $l->t('Science Mesh'),
            ];
        });
    }

    /**
     * @return ScienceMeshShareProvider
     * @throws QueryException
     */
    public function getScienceMeshShareProvider(): ScienceMeshShareProvider
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
