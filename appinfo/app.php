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

declare(strict_types=1);

use OCA\ScienceMesh\AppInfo\ScienceMeshApp;

$app = OC::$server->query(ScienceMeshApp::class);

OC::$server->getNavigationManager()->add(function () {
    $urlGenerator = OC::$server->getURLGenerator();

    return [
        // The string under which your app will be referenced in owncloud
        "id" => "sciencemesh",

        // The sorting weight for the navigation.
        // The higher the number, the higher will it be listed in the navigation
        "order" => 10,

        // The route that will be shown on startup
        "href" => $urlGenerator->linkToRoute("sciencemesh.app.contacts"),

        // The icon that will be shown in the navigation, located in img/
        "icon" => $urlGenerator->imagePath("sciencemesh", "app-white.svg"),

        // The application's title, used in the navigation & the settings page of your app
        "name" => OC::$server->getL10N("sciencemesh")->t("ScienceMesh"),
    ];
});
