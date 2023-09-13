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

use Exception;
use OCA\ScienceMesh\Controller\PageController;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\QueryException;


class Application extends App
{
    public function __construct(array $urlParams = array())
    {
        parent::__construct('sciencemesh', $urlParams);

        $container = $this->getContainer();

        $container->registerService('PageController', function ($c) {
            return new PageController(
                $c->query('AppName'),
                $c->query('Request')
            );
        });
    }

    /**
     * @throws QueryException
     * @throws Exception
     */
    public function getScienceMeshShareProvider(): ScienceMeshShareProvider
    {
        $container = $this->getContainer();

        $connection = $container->query("OCP\IDBConnection");
        $eventDispatcher = $container->query("Symfony\Component\EventDispatcher\EventDispatcherInterface");
        $addressHandler = $container->query("OCA\FederatedFileSharing\AddressHandler");
        $notifications = $container->query("OCA\FederatedFileSharing\Notifications");
        $tokenHandler = $container->query("OCA\FederatedFileSharing\TokenHandler");
        $l10n = $container->query("OCP\IL10N");
        $logger = $container->query("OCP\ILogger");
        $rootFolder = $container->query("OCP\Files\IRootFolder");
        $config = $container->query("OCP\IConfig");
        $userManager = $container->query("OCP\IUserManager");

        return new ScienceMeshShareProvider(
            $connection,
            $eventDispatcher,
            $addressHandler,
            $notifications,
            $tokenHandler,
            $l10n,
            $logger,
            $rootFolder,
            $config,
            $userManager
        );
    }
}
