<?php
	namespace OCA\ScienceMesh;

	use OCP\IConfig;
	use OCP\IUserManager;
	use OCP\IUrlGenerator;

	/**
	 * @package OCA\ScienceMesh
	 */
	class ServerConfig {

		/** @var IConfig */
		private $config;

		/**
		 * @param IConfig $config
		 */
		public function __construct(IConfig $config, IUrlGenerator $urlGenerator, IUserManager $userManager) {
			$this->config = $config;
			$this->userManager = $userManager;
			$this->urlGenerator = $urlGenerator;
		}

		/**
		 * @return string
		 */
		public function getPrivateKey() {
			$result = $this->config->getAppValue('sciencemesh','privateKey');
			if (!$result) {
				// generate and save a new set if we don't have a private key;
				$keys = $this->generateKeySet();
				$this->config->setAppValue('sciencemesh','privateKey',$keys['privateKey']);
				$this->config->setAppValue('sciencemesh','encryptionKey',$keys['encryptionKey']);
			}
			return $this->config->getAppValue('sciencemesh','privateKey');
		}

		/**
		 * @param string $privateKey
		 */
		public function setPrivateKey($privateKey) {
			$this->config->setAppValue('sciencemesh','privateKey',$privateKey);
		}

		/**
		 * @return string
		 */
		public function getEncryptionKey() {
			return $this->config->getAppValue('sciencemesh','encryptionKey');
		}

		/**
		 * @param string $publicKey
		 */
		public function setEncryptionKey($publicKey) {
			$this->config->setAppValue('sciencemesh','encryptionKey',$publicKey);
		}

		private function generateKeySet() {
			$config = array(
				"digest_alg" => "sha256",
				"private_key_bits" => 2048,
				"private_key_type" => OPENSSL_KEYTYPE_RSA,
			);
			// Create the private and public key
			$key = openssl_pkey_new($config);

			// Extract the private key from $key to $privateKey
			openssl_pkey_export($key, $privateKey);
			$encryptionKey = base64_encode(random_bytes(32));
			$result = array(
				"privateKey" => $privateKey,
				"encryptionKey" => $encryptionKey
			);
			return $result;
		}
	}