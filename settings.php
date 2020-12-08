<?php

namespace OCA\ScienceMesh;

use OCP\User;

use OCA\ScienceMesh\Controller\SettingsController;

User::checkAdminUser();

$response = \OC::$server->query(SettingsController::class)->index();

return $response->render();
