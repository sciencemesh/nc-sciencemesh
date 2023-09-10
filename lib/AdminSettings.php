<?php

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
