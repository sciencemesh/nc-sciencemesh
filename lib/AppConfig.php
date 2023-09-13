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

namespace OCA\ScienceMesh;

use OC;
use OCP\IConfig;
use OCP\ILogger;

/**
 * Application configuration
 *
 * @package OCA\ScienceMesh
 */
class AppConfig
{

    /**
     * Application name
     *
     * @var string
     */
    private string $appName;

    /**
     * Config service
     *
     * @var IConfig
     */
    private IConfig $config;

    /**
     * Logger
     *
     * @var ILogger
     */
    private ILogger $logger;

    /**
     * @param string $AppName - application name
     */
    public function __construct(string $AppName)
    {

        $this->appName = $AppName;

        $this->config = OC::$server->getConfig();
        $this->logger = OC::$server->getLogger();
    }

    public function GetConfigValue($key)
    {
        return $this->config->getSystemValue($this->appName)[$key];
    }

    public function SetConfigValue($key, $value)
    {
        $this->config->setAppValue($this->appName, $key, $value);
    }
}
