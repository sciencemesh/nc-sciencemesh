<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Navid Shokri <navid.pdp11@gmail.com>
 * @author Michiel De Jong <michiel@pondersource.com>
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\GlobalConfig;

use OCP\IConfig;

class GlobalScaleConfig implements IGlobalScaleConfig
{

    /** @var IConfig */
    private IConfig $config;

    /**
     * Config constructor.
     *
     * @param IConfig $config
     */
    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    /**
     * check if federation should only be used internally in a global scale setup
     *
     * @return bool
     * @since 12.0.1
     */
    public function onlyInternalFederation(): bool
    {
        // if global scale is disabled federation works always globally
        $gsEnabled = $this->isGlobalScaleEnabled();
        if ($gsEnabled === false) {
            return false;
        }

        $enabled = $this->config->getSystemValue('gs.federation', 'internal');

        return $enabled === 'internal';
    }

    /**
     * check if global scale is enabled
     *
     * @return bool
     * @since 12.0.1
     */
    public function isGlobalScaleEnabled(): bool
    {
        $enabled = $this->config->getSystemValue('gs.enabled', false);
        return $enabled !== false;
    }
}
