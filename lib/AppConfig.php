<?php

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
