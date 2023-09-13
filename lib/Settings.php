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
use OCP\Template;

class Settings implements ISettings
{
    private Serverconfig $config;

    public function __construct(Serverconfig $config)
    {
        $this->config = $config;
    }

    public function getForm()
    {
        $response = new TemplateResponse('sciencemesh', 'settings-admin');
        $response->setParams([
            'apiKey' => $this->config->getApiKey(),
            'siteName' => $this->config->getSiteName(),
            'siteUrl' => $this->config->getSiteUrl(),
            'siteId' => $this->config->getSiteId(),
            'country' => $this->config->getCountry(),
            'iopUrl' => $this->config->getIopUrl(),
            'numUsers' => $this->config->getNumUsers(),
            'numFiles' => $this->config->getNumFiles(),
            'numStorage' => $this->config->getNumStorage()
        ]);
        return $response;
    }

    public function getSection(): string
    {
        return 'sharing';
    }

    public function getPriority(): int
    {
        return 50;
    }

    /**
     * The panel controller method that returns a template to the UI
     * @return TemplateResponse | Template
     * @since 10.0
     */
    public function getPanel()
    {
        // TODO: Implement getPanel() method.
    }

    /**
     * A string to identify the section in the UI / HTML and URL
     * @return string
     * @since 10.0
     */
    public function getSectionID()
    {
        // TODO: Implement getSectionID() method.
    }
}
