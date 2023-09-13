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
     * @return TemplateResponse
     * @throws Exception
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

    public function getSectionID(): string
    {
        return 'sciencemesh_settings'; // Name of the previously created section.
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority(): int
    {
        return 10;
    }
}