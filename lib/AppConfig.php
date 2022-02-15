<?php

namespace OCA\ScienceMesh;

use \DateInterval;
use \DateTime;

use OCP\IConfig;
use OCP\ILogger;

/**
 * Application configutarion
 *
 * @package OCA\ScienceMesh
 */
class AppConfig {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * Config service
     *
     * @var IConfig
     */
    private $config;

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * @param string $AppName - application name
     */
    public function __construct($AppName) {

        $this->appName = $AppName;

        $this->config = \OC::$server->getConfig();
        $this->logger = \OC::$server->getLogger();
    }

    public function GetConfigValue($key) {
        return $this->config->getSystemValue($this->appName)[$key];
    }

    public function SetConfigValue($key, $value) {
        $this->config->setAppValue($this->appName, $key, $value);
    }
}
