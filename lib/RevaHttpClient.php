<?php
/**
 * @copyright Copyright (c) 2021, PonderSource
 *
 * @author Yvo Brevoort <yvo@pondersource.nl>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\ScienceMesh;

use OCP\IConfig;

/**
 * Class RevaHttpClient
 *
 * This class is a helper to handle the outbound HTTP connections from Nextcloud to Reva
 *
 * @package OCA\ScienceMesh\RevaHttpClient
 */
class RevaHttpClient {
	private $client;
	private $revaUrl;
	private $revaLoopbackSecret;
		
	/**
	 * RevaHttpClient constructor.
	 *
	 */
	public function __construct(IConfig $config, $curlDebug = true) {
		$this->config = $config;
		$this->serverConfig = new \OCA\ScienceMesh\ServerConfig($config);
		$this->revaUrl = $this->serverConfig->getIopUrl();
		$this->revaLoopbackSecret = $this->serverConfig->getRevaLoopbackSecret();
		$this->curlDebug = $curlDebug;
	}

	private function curlGet($url, $user, $params = []) {
		$ch = curl_init();
		if (sizeof($params)) {
			$url .= "?" . http_build_query($params);
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($this->revaLoopbackSecret) {
			curl_setopt($ch, CURLOPT_USERPWD, $user.":".$this->revaLoopbackSecret);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		}

		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		error_log('curl output:' . var_export($output, true) . ' info: ' . var_export($info, true));
		curl_close($ch);

		return $output;
	}
	private function curlPost($url, $user, $params = []) {
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		// curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_PRETTY_PRINT));
		if ($this->revaLoopbackSecret) {
			curl_setopt($ch, CURLOPT_USERPWD, $user.":".$this->revaLoopbackSecret);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		}
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		error_log('curl output:' . var_export($output, true) . ' info: ' . var_export($info, true));
		curl_close($ch);
		return $output;
	}

	private function revaGet($route, $user, $params = []) {
		$url = $this->revaUrl . $route;
		return $this->curlGet($url, $user, $params);
	}
		
	private function revaPost($route, $user, $params = []) {
		$url = $this->revaUrl . $route;
		return $this->curlPost($url, $user, $params);
	}

	public function createShare($user, $params) {
		error_log("RevaHttpClient createShare");
		// see https://github.com/cs3org/reva/pull/3695/files#diff-6df5ade636cf2b09c52181e29ca2257dc3426f7ea7e0a5dcbaad527c0b648ff5R55-R60
		if (!isset($params['sourcePath'])) {
			throw new \Exception("Missing sourcePath", 400);
		}
		if (!isset($params['targetPath'])) {
			throw new \Exception("Missing targetPath", 400);
		}
		if (!isset($params['type'])) {
			throw new \Exception("Missing type", 400);
		}
		if (!isset($params['role'])) {
			$params['role'] = 'viewer';
		}
		if (!isset($params['recipientUsername'])) {
			throw new \Exception("Missing recipientUsername", 400);
		}
		if (!isset($params['recipientHost'])) {
			throw new \Exception("Missing recipientHost", 400);
		}
		// $params["loginType"] = "basic";
		// $params["loginUsername"] = $user;
		// $params["loginPassword"] = "ha"; //$this->revaLoopbackSecret;
		error_log("Calling reva/sciencemesh/create-share " . json_encode($params));
		$responseText = $this->revaPost('sciencemesh/create-share', $user, $params);
		return json_decode($responseText);
	}

	public function ocmProvider($userId) {
		return $this->revaGet('ocm-provider', $userId);
	}

	public function findAcceptedUsers($userId) {
		$users = $this->revaGet('sciencemesh/find-accepted-users', $userId);
		error_log("users " . var_export($users, true));
		if ($users === "null\n") {
			error_log("users corrected!");
			$users = "[]";
		}
		return $users;
	}

	public function acceptInvite($providerDomain, $token, $userId) {
		$empty = $this->revaPost('sciencemesh/accept-invite', $userId, [
			'providerDomain' => $providerDomain,
			'token' => $token
		]);
		return "Accepted invite";
	}

	public function generateTokenFromReva($userId) {
		$tokenFromReva = $this->revaGet('sciencemesh/generate-invite', $userId);
		error_log('Got token from reva!' . $tokenFromReva);
		return json_decode($tokenFromReva, true);
	}
}