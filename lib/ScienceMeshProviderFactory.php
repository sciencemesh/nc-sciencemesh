<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Navid Shokri <navid.pdp11@gmail.org>
 * @author Michiel De Jong <michiel@pondersource.com>
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh;

use Exception;
use OC\Share\Constants;
use OC\Share20\DefaultShareProvider;
use OC\Share20\Exception\ProviderException;
use OCA\ScienceMesh\AppInfo\ScienceMeshApp;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\AppFramework\QueryException;
use OCP\IServerContainer;
use OCP\Share\IProviderFactory;

/**
 * Class ScienceMeshProviderFactory
 *
 * @package OCA\ScienceMesh
 */
class ScienceMeshProviderFactory implements IProviderFactory
{
    /** @var IServerContainer */
    private IServerContainer $serverContainer;

    /** @var ?DefaultShareProvider */
    private ?DefaultShareProvider $defaultProvider = null;

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
            $this->scienceMeshShareProvider()
        ];
    }

    /**
     * Create the default share provider.
     *
     * @return DefaultShareProvider
     */
    protected function defaultShareProvider(): DefaultShareProvider
    {
        if ($this->defaultProvider === null) {
            $this->defaultProvider = new DefaultShareProvider(
                $this->serverContainer->getDatabaseConnection(),
                $this->serverContainer->getUserManager(),
                $this->serverContainer->getGroupManager(),
                $this->serverContainer->getLazyRootFolder()
            );
        }
        return $this->defaultProvider;
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
            if (!$appManager->isEnabledForUser('sciencemesh')) {
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
     * @inheritdoc
     * @throws QueryException
     */
    public function getProvider($id)
    {
        $provider = null;
        if ($id === 'ocinternal') {
            $provider = $this->defaultShareProvider();
        } elseif ($id === 'ocFederatedSharing' || $id === 'sciencemesh') {
            $provider = $this->scienceMeshShareProvider();
        }

        if ($provider === null) {
            throw new ProviderException('No provider with id ' . $id . ' found.');
        }

        return $provider;
    }

    /**
     * @inheritdoc
     * @throws QueryException
     */
    public function getProviderForType($shareType)
    {
        // TODO: @Mahdi possible conflict with rd-sram as Constants::SHARE_TYPE_GROUP is not handled by sciencemesh.
        if ($shareType === Constants::SHARE_TYPE_REMOTE) {
            $provider = $this->scienceMeshShareProvider();
        } else {
            $provider = $this->defaultShareProvider();
        }

        if ($provider === null) {
            throw new ProviderException('No share provider for share type ' . $shareType);
        }

        return $provider;
    }
}
