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

use Exception;
use OCP\IConfig;
use RangeException;

/**
 * @throws Exception
 */
function random_str(
    int    $length = 64,
    string $keyspace = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
): string
{
    if ($length < 1) {
        throw new RangeException("Length must be a positive integer");
    }
    $pieces = [];
    $max = mb_strlen($keyspace, "8bit") - 1;
    for ($i = 0; $i < $length; ++$i) {
        $pieces [] = $keyspace[random_int(0, $max)];
    }
    return implode("", $pieces);
}

/**
 * @package OCA\ScienceMesh
 */
class ServerConfig
{

    /** @var IConfig */
    private IConfig $config;

    /**
     * @param IConfig $config
     */
    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    public function getApiKey()
    {
        return $this->config->getAppValue("sciencemesh", "apiKey");
    }

    public function getSiteName()
    {
        return $this->config->getAppValue("sciencemesh", "siteName");
    }

    public function getSiteUrl()
    {
        return $this->config->getAppValue("sciencemesh", "siteUrl");
    }

    public function getSiteId()
    {
        return $this->config->getAppValue("sciencemesh", "siteId");
    }

    public function getCountry()
    {
        return $this->config->getAppValue("sciencemesh", "country");
    }

    public function getIopUrl(): string
    {
        return rtrim($this->config->getAppValue("sciencemesh", "iopUrl"), "/") . "/";
    }

    public function getIopIdp(): string
    {
        // TODO: @Mahdi use function from utils.
        // converts https://revaowncloud1.docker/ to revaowncloud1.docker
        // NOTE: do not use it on anything without http(s) in the start, it would return null.
        return str_ireplace("www.", "", parse_url($this->getIopUrl(), PHP_URL_HOST));
    }

    /**
     * @throws Exception
     */
    public function getRevaLoopbackSecret()
    {
        $ret = $this->config->getAppValue("sciencemesh", "revaLoopbackSecret");
        if (!$ret) {
            $ret = random_str(32);
            $this->config->setAppValue("sciencemesh", "revaLoopbackSecret", $ret);
        }
        return $ret;
    }

    /**
     * @throws Exception
     */
    public function getRevaSharedSecret()
    {
        $ret = $this->config->getAppValue("sciencemesh", "revaSharedSecret");
        if (!$ret) {
            $ret = random_str(32);
            $this->config->setAppValue("sciencemesh", "revaSharedSecret", $ret);
        }
        return $ret;
    }

    public function setRevaSharedSecret($sharedSecret)
    {
        $this->config->setAppValue("sciencemesh", "revaSharedSecret", $sharedSecret);
    }

    public function getNumUsers()
    {
        return $this->config->getAppValue("sciencemesh", "numUsers");
    }

    public function getNumFiles()
    {
        return $this->config->getAppValue("sciencemesh", "numFiles");
    }

    public function getNumStorage()
    {
        return $this->config->getAppValue("sciencemesh", "numStorage");
    }

    public function setApiKey($apiKey)
    {
        $this->config->setAppValue("sciencemesh", "apiKey", $apiKey);
    }

    public function setSiteName($siteName)
    {
        $this->config->setAppValue("sciencemesh", "siteName", $siteName);
    }

    public function setSiteUrl($siteUrl)
    {
        $this->config->setAppValue("sciencemesh", "siteUrl", $siteUrl);
    }

    public function setSiteId($siteId)
    {
        $this->config->setAppValue("sciencemesh", "siteId", $siteId);
    }

    public function setCountry($country)
    {
        $this->config->setAppValue("sciencemesh", "country", $country);
    }

    public function setIopUrl($iopUrl)
    {
        $this->config->setAppValue("sciencemesh", "iopUrl", $iopUrl);
    }

    public function setNumUsers($numUsers)
    {
        $this->config->setAppValue("sciencemesh", "numUsers", $numUsers);
    }

    public function setNumFiles($numFiles)
    {
        $this->config->setAppValue("sciencemesh", "numFiles", $numFiles);
    }

    public function setNumStorage($numStorage)
    {
        $this->config->setAppValue("sciencemesh", "numStorage", $numStorage);
    }
}
