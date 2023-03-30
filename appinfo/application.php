<?php 

namespace OCA\ScienceMesh\AppInfo;

use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use \OCP\AppFramework\App;
use \OCA\ScienceMesh\Controller\PageController;



class Application extends App {
    public function __construct(array $urlParams=array()){
        parent::__construct('sciencemesh', $urlParams);

        $container = $this->getContainer();
        $container->registerService('PageController', function($c) {
            return new PageController(
                $c->query('AppName'),
                $c->query('Request')
            );
        });
    }

    public function getScienceMeshShareProvider()
    {
        $container = $this->getContainer();

        $connection = $container->query("OCP\IDBConnection");
        $l10n = $container->query("OCP\IL10N");
        $logger = $container->query("OCP\ILogger");
        $rootFolder = $container->query("OCP\Files\IRootFolder");
        $config = $container->query("OCP\IConfig");
        $userManager = $container->query("OCP\IUserManager");

        return new ScienceMeshShareProvider($connection, $l10n, $logger, $rootFolder,$config,$userManager);
    }
}