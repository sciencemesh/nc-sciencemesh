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

/**
 * Interface IConfig
 *
 * Configuration of the global scale architecture
 *
 * @since 12.0.1
 */
interface IGlobalScaleConfig
{

    /**
     * check if global scale is enabled
     *
     * @return bool
     * @since 12.0.1
     */
    public function isGlobalScaleEnabled(): bool;

    /**
     * check if federation should only be used internally in a global scale setup
     *
     * @return bool
     * @since 12.0.1
     */
    public function onlyInternalFederation(): bool;
}
