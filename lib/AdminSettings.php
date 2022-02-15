<?php

namespace OCA\ScienceMesh;

use OCP\Settings\ISettings;

/**
 * Settings controller for the administration page
 */
class AdminSettings implements ISettings {

    public function __construct() {
    }

    /**
     * Print config section
     *
     * @return TemplateResponse
     */
    public function getPanel() {
        return $this->getForm();
    }

    /**
     * Get section ID
     *
     * @return string
     */
    public function getSectionID() {
        return "general";
    }

    /**
     * Get priority order
     *
     * @return int
     */
    public function getPriority() {
        return 50;
    }
    
    public function getSection() {
        return null;
    }
    
    public function getForm() {
        return null;
    }
}
