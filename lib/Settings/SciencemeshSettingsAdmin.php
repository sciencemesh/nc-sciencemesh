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

namespace OCA\ScienceMesh\Settings;

use Exception;
use OCA\ScienceMesh\ServerConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class SciencemeshSettingsAdmin implements ISettings
{
    private IConfig $config;
    private ServerConfig $serverConfig;

    public function __construct(IConfig $config)
    {
        $this->config = $config;
        $this->serverConfig = new ServerConfig($config);

    }

    /**
     * The panel controller method that returns a template to the UI
     * @return TemplateResponse
     * @throws Exception
     * @since 10.0
     */
    public function getPanel(): TemplateResponse
    {
        $parameters = [
            'sciencemeshSetting' => $this->config->getSystemValue('sciencemesh_advance_settings', true),
            'sciencemeshIopUrl' => $this->serverConfig->getIopUrl(),
            'sciencemeshRevaSharedSecret' => $this->serverConfig->getRevaSharedSecret()
        ];

        return new TemplateResponse('sciencemesh', 'settings/admin', $parameters, '');
    }

    /**
     * A string to identify the section in the UI / HTML and URL
     * @return string
     * @since 10.0
     */
    public function getSectionID(): string
    {
        return 'sciencemesh_settings'; // Name of the previously created section.
    }

    /**
     * The number used to order the section in the UI.
     * @return int between 0 and 100, with 100 being the highest priority
     * @since 10.0
     */
    public function getPriority(): int
    {
        return 10;
    }
}
