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

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

/**
 * Settings controller for the administration page
 */
class AdminSettings implements ISettings
{

    public function __construct()
    {
    }

    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function getPanel(): ?TemplateResponse
    {
        return $this->getForm();
    }

    public function getForm()
    {
        return null;
    }

    /**
     * Get section ID
     *
     * @return string
     */
    public function getSectionID(): string
    {
        return "general";
    }

    /**
     * Get priority order
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 50;
    }

    public function getSection()
    {
        return null;
    }
}
