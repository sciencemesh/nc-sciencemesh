<?php
declare(strict_types=1);
use OCP\Util;

$app = \OC::$server->query(\OCA\ScienceMesh\AppInfo\ScienceMeshApp::class);

$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function(){
    Util::addScript('sciencemesh', 'open-with');
    Util::addStyle('sciencemesh', 'open-with');
});
