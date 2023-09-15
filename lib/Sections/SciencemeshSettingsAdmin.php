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

namespace OCA\ScienceMesh\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISection;

class SciencemeshSettingsAdmin implements ISection
{
    private IL10N $l;
    private IURLGenerator $urlGenerator;

    public function __construct(IL10N $l, IURLGenerator $urlGenerator)
    {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
    }

    public function getIconName(): string
    {
        return $this->urlGenerator->imagePath('core', 'actions/settings-dark.svg');
    }

    public function getID(): string
    {
        return 'sciencemesh_settings';
    }

    public function getName(): string
    {
        return $this->l->t('ScienceMesh Settings');
    }

    public function getPriority(): int
    {
        return 1;
    }
}