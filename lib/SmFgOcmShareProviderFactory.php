<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh;

use Exception;
use OC\Share\Constants;
use OC\Share20\DefaultShareProvider;
use OC\Share20\Exception\ProviderException;
use OCA\FederatedGroups\AppInfo\Application;
use OCA\FederatedGroups\MixedGroupShareProvider;
use OCA\FederatedGroups\SRAMFederatedGroupShareProvider;
use OCA\ScienceMesh\AppInfo\ScienceMeshApp;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\AppFramework\QueryException;
use OCP\IServerContainer;
use OCP\Share\IProviderFactory;

/**
 * Class SmFgOcmShareProviderFactory
 *
 */
class SmFgOcmShareProviderFactory implements IProviderFactory
{
    /** @var IServerContainer */
    private IServerContainer $serverContainer;

    /** @var ?DefaultShareProvider */
    private ?DefaultShareProvider $defaultShareProvider = null;

    /** @var ?SRAMFederatedGroupShareProvider */
    private ?SRAMFederatedGroupShareProvider $federatedGroupShareProvider = null;

    /** @var ?MixedGroupShareProvider */
    private ?MixedGroupShareProvider $mixedGroupShareProvider = null;

    /** @var ?ScienceMeshShareProvider */
    private ?ScienceMeshShareProvider $scienceMeshShareProvider = null;

    /**
     * IProviderFactory constructor.
     * @param IServerContainer $serverContainer
     */
    public function __construct(IServerContainer $serverContainer)
    {
        $this->serverContainer = $serverContainer;
    }

    /**
     * @throws QueryException
     */
    public function getProviders(): array
    {
        return [
            $this->defaultShareProvider(),
            $this->mixedGroupShareProvider(),
            $this->scienceMeshShareProvider(),
            $this->federatedGroupShareProvider()
        ];
    }

    /**
     * Create the default share provider.
     *
     * @return DefaultShareProvider
     */
    protected function defaultShareProvider(): DefaultShareProvider
    {
        if ($this->defaultShareProvider === null) {
            $this->defaultShareProvider = new DefaultShareProvider(
                $this->serverContainer->getDatabaseConnection(),
                $this->serverContainer->getUserManager(),
                $this->serverContainer->getGroupManager(),
                $this->serverContainer->getLazyRootFolder()
            );
        }
        return $this->defaultShareProvider;
    }

    /**
     * Create the sciencemesh share provider
     *
     * @return ScienceMeshShareProvider
     * @throws QueryException|Exception
     */
    protected function scienceMeshShareProvider(): ?ScienceMeshShareProvider
    {
        if ($this->scienceMeshShareProvider === null) {
            /*
             * Check if the app is enabled
             */
            $appManager = $this->serverContainer->getAppManager();
            if (!$appManager->isEnabledForUser("sciencemesh")) {
                // TODO: @Mahdi what if sciencemesh is disabled and federatedfilesharing is enabled?
                // we are overriding the base share provider, so if sciencemesh is disabled all
                // federated share capability will be disabled.
                return null;
            }

            $scienceMeshApplication = new ScienceMeshApp();
            $this->scienceMeshShareProvider = $scienceMeshApplication->getScienceMeshShareProvider();
        }
        return $this->scienceMeshShareProvider;
    }

    /**
     * Create the federated share provider for OCM to groups
     *
     * @return SRAMFederatedGroupShareProvider
     */
    protected function federatedGroupShareProvider(): SRAMFederatedGroupShareProvider
    {
        if ($this->federatedGroupShareProvider === null) {
            $app = new Application();
            $this->federatedGroupShareProvider = $app->getSRAMFederatedGroupShareProvider();
        }
        return $this->federatedGroupShareProvider;
    }

    /**
     * Create the mixed group share provider for OCM to groups
     *
     * @return MixedGroupShareProvider
     */
    protected function mixedGroupShareProvider(): MixedGroupShareProvider
    {
        if ($this->mixedGroupShareProvider === null) {
            $this->mixedGroupShareProvider = Application::getMixedGroupShareProvider();
        }
        return $this->mixedGroupShareProvider;
    }

    /**
     * @inheritdoc
     * @throws QueryException
     */
    public function getProvider($id)
    {
        $provider = null;

        if ($id === "ocinternal" || $id === "ocMixFederatedSharing") {
            $provider = $this->mixedGroupShareProvider();
        } elseif ($id === "ocFederatedSharing" || $id === "sciencemesh") {
            $provider = $this->scienceMeshShareProvider();
        } elseif ($id === "ocGroupFederatedSharing") {
            $provider = $this->federatedGroupShareProvider();
        }


        if ($provider === null) {
            throw new ProviderException("No provider with id " . $id . " found.");
        }

        return $provider;
    }

    /**
     * @inheritdoc
     * @throws QueryException
     */
    public function getProviderForType($shareType)
    {
        $provider = null;

        if ($shareType === Constants::SHARE_TYPE_USER ||
            $shareType === Constants::SHARE_TYPE_LINK ||
            $shareType === Constants::SHARE_TYPE_GUEST ||
            $shareType === Constants::SHARE_TYPE_CONTACT ||
            $shareType === Constants::SHARE_TYPE_GROUP) {
            $provider = $this->mixedGroupShareProvider();
        } elseif ($shareType === Constants::SHARE_TYPE_REMOTE) {
            $provider = $this->scienceMeshShareProvider();
        } elseif ($shareType === Constants::SHARE_TYPE_REMOTE_GROUP) {
            $provider = $this->federatedGroupShareProvider();
        }

        if ($provider === null) {
            throw new ProviderException("No share provider for share type " . $shareType);
        }

        return $provider;
    }
}
