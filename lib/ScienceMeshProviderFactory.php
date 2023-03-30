<?php
/**
 * @author Navid Shokri <navid.pdp11@gmail.org>

 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\ScienceMesh;

use OC\Share20\DefaultShareProvider;
use OCA\ScienceMesh\AppInfo\Application;
use OCA\ScienceMesh\ShareProvider\ScienceMeshShareProvider;
use OCP\Share\IProviderFactory;
use OC\Share20\Exception\ProviderException;
use OCP\IServerContainer;

/**
 * Class ProviderFactory
 *
 * @package OC\Share20
 */
class ScienceMeshProviderFactory implements IProviderFactory {

	/** @var IServerContainer */
	private $serverContainer;
	/** @var DefaultShareProvider */
	private $defaultProvider = null;
	/** @var ScienceMeshShareProvider */
	private $scienceMeshShareProvider = null;

	/**
	 * IProviderFactory constructor.
	 * @param IServerContainer $serverContainer
	 */
	public function __construct(IServerContainer $serverContainer) {
		$this->serverContainer = $serverContainer;
	}

	/**
	 * Create the default share provider.
	 *
	 * @return DefaultShareProvider
	 */
	protected function defaultShareProvider() {
		if ($this->defaultProvider === null) {
			// serverContainer really has to be more than just an IServerContainer
			// because getLazyRootFolder() is only in \OC\Server
			'@phan-var \OC\Server $this->serverContainer';
			$this->defaultProvider = new DefaultShareProvider(
				$this->serverContainer->getDatabaseConnection(),
				$this->serverContainer->getUserManager(),
				$this->serverContainer->getGroupManager(),
				$this->serverContainer->getLazyRootFolder()
			);
		}
    error_log("ScienceMeshProviderFactory returns its default share provider");
		return $this->defaultProvider;
	}

	/**
	 * Create the federated share provider
	 *
	 * @return ScienceMeshShareProvider
	 */
	protected function scienceMeshShareProvider() {
		if ($this->scienceMeshShareProvider === null) {
			/*
			 * Check if the app is enabled
			 */
			$appManager = $this->serverContainer->getAppManager();
			//** change to science mesh  */
            if (!$appManager->isEnabledForUser('sciencemesh')) {
				return null;
			}

			/*
			 * TODO: add factory to federated sharing app
			 */
			$scienceMeshApplication = new Application();
			$this->scienceMeshShareProvider = $scienceMeshApplication->getScienceMeshShareProvider();
		}
    error_log("ScienceMeshProviderFactory returns its ScienceMesh share provider");

		return $this->scienceMeshShareProvider;
	}

	public function getProviders() {
		return [
			$this->defaultShareProvider(),
			$this->scienceMeshShareProvider()
		];
	}

	/**
	 * @inheritdoc
	 */
	public function getProvider($id) {
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
	 */
	public function getProviderForType($shareType) {
		$provider = null;

		if ($shareType === \OCP\Share::SHARE_TYPE_USER  ||
			$shareType === \OCP\Share::SHARE_TYPE_GROUP ||
			$shareType === \OCP\Share::SHARE_TYPE_LINK) {
			error_log("ScienceMeshProviderFactory returns a share provider for local");
			$provider = $this->defaultShareProvider();
		} elseif ($shareType === \OCP\Share::SHARE_TYPE_REMOTE) {
			error_log("ScienceMeshProviderFactory returns a share provider for remote");
			$provider = $this->scienceMeshShareProvider();
		}

		if ($provider === null) {
			throw new ProviderException('No share provider for share type ' . $shareType);
		}

		return $provider;
	}
}
