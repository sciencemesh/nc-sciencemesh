<?php
namespace OCA\ScienceMesh\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\ISection;

class SciencemeshSettingsAdmin implements ISection {
    private IL10N $l;
    private IURLGenerator $urlGenerator;

    public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
    }

    public function getIconName(): string {
        return $this->urlGenerator->imagePath('core', 'actions/settings-dark.svg');
    }

    public function getID(): string {
        return 'sciencemesh_settings';
    }

    public function getName(): string {
        return $this->l->t('ScienceMesh Settings');
    }

    public function getPriority(): int {
        return 1;
    }
}