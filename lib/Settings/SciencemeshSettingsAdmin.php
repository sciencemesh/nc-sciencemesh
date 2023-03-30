<?php
namespace OCA\ScienceMesh\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCA\Sciencemesh\ServerConfig;

class SciencemeshSettingsAdmin implements ISettings {
    private IL10N $l;
    private IConfig $config;

    public function __construct(IConfig $config,
                                IL10N $l
                                ) {
        $this->config = $config;
        $this->l = $l;
        $this->serverConfig = new \OCA\ScienceMesh\ServerConfig($config);

    }

    /**
     * @return TemplateResponse
     */
    public function getPanel() {
        $parameters = [
            'sciencemeshSetting' => $this->config->getSystemValue('sciencemesh_advance_settings', true),
            'sciencemeshIopUrl' => $this->serverConfig->getIopUrl(),
            'sciencemeshRevaSharedSecret' => $this->serverConfig->getRevaSharedSecret()
        ];
        
        return new TemplateResponse('sciencemesh', 'settings/admin', $parameters, '');
    }

    public function getSectionID() {
        return 'sciencemesh_settings'; // Name of the previously created section.
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority() {
        return 10;
    }
}