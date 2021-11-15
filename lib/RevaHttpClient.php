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
	private $revaUser;
	private $revePass;
		
	/**
	 * RevaHttpClient constructor.
	 *
	 */
	public function __construct() {
		$this->revaUrl = "https://revanc1.docker/ocm/"; // FIXME: Read from config
		$this->revaUser = "einstein"; // FIXME: Read from config, or generate if this loops back to us anyway;
		$this->revaPass = "relativity"; // FIXME: Read from config, or generate if this loops back to us anyway;
	}

	private function curlGet($url, $params = []) {
		$ch = curl_init();
		if (sizeof($params)) {
			$url .= "?" . http_build_query($params);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($this->revaUser && $this->revaPass) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->revaUser.":".$this->revaPass);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		}
		$output = curl_exec($ch);
		curl_close($ch);
		
		return $output;
	}
	private function curlPost($url, $params = []) {
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_PRETTY_PRINT));
		if ($this->revaUser && $this->revaPass) {
			curl_setopt($ch, CURLOPT_USERPWD, $this->revaUser.":".$this->revaPass);
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		}
		$output = curl_exec($ch);
		curl_close($ch);

		return $output;
	}

	public function revaGet($method, $params = []) {
		$url = $this->revaUrl . $method;
		return $this->curlGet($url, $params);
	}
		
	public function revaPost($method, $params = []) {
		$url = $this->revaUrl . $method;
		return $this->curlPost($url, $params);
	}
	
	public function createShare($params) {
		if (!isset($params['path'])) {
			throw new Exception("Missing path", 400);
		}
		if (!isset($params['recipientUsername'])) {
			throw new Exception("Missing recipientUsername", 400);
		}
		if (!isset($params['recipientHost'])) {
			throw new Exception("Missing recipientHost", 400);
		}
		return $this->revaPost('send', $params);
	}
}
