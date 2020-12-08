<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\ScienceMesh\AppInfo;

use OCP\AppFramework\App;
use OCP\Files\IMimeTypeDetector;
use OCP\Util;
use OCP\IPreview;

use OCA\ScienceMesh\AppConfig;
use OCA\ScienceMesh\Controller\PageController;
use OCA\ScienceMesh\Controller\SettingsController;

class Application extends App {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    public $appConfig;

    /**
     * Hash generator
     *
     * @var Crypt
     */
    public $crypt;

    public $container;

    public function __construct(array $urlParams = []) {
        $appName = "sciencemesh";

        parent::__construct($appName, $urlParams);

        $this->appConfig = new AppConfig($appName);
        $container = $this->getContainer();
    }
}
