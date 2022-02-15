<?php

namespace OCA\ScienceMesh\User;

class ScienceMeshUserId {
	public const USER_TYPE_INVALID = 0;
	public const USER_TYPE_PRIMARY = 1;
	public const USER_TYPE_SECONDARY = 2;
	public const USER_TYPE_SERVICE = 3;
	public const USER_TYPE_APPLICATION = 4;
	public const USER_TYPE_GUEST = 5;
	public const USER_TYPE_FEDERATED = 6;
	public const USER_TYPE_LIGHTWEIGHT = 7;

	private $idp;
	private $opaque_id;
	private $type;

	public function __construct($idp, $opaque_id, $type) {
		$this->idp = $idp;
		$this->opaque_id = $opaque_id;
		$this->type = $type;
	}

	public static function fromJson($json) {
		$id = json_decode($json, true);
		$error = json_last_error();
		if ($error) {
			throw new \InvalidArgumentException(
				__CLASS__ .
				': Failed to parse JSON. $json: ' .
				$json .
				' json_last_error Code: ' .
				$error
			);
		}
		if (isset($id['idp']) &&
			 isset($id['opaque_id']) &&
			 isset($id['type'])) {
			$idp = $id['idp'];
			$opaque_id = $id['opaque_id'];
			$type = $id['type'];
			return new ScienceMeshUserId($idp, $opaque_id, $type);
		} else {
			throw new \DomainException(
				__CLASS__ .
				'::fromJson: unable to parse ' .
				$json .
				' into ScienceMeshUserId, necessary fields are $idp, $opaque_id and $type.'
			);
		}
	}

	public static function fromArray($arr) {
		$json = json_encode($arr);
		return ScienceMeshUserId::fromJson($json);
	}

	public function asArray() {
		return [
			'idp' => $this->idp,
			'opaque_id' => $this->opaque_id,
			'type' => $this->type
		];
	}

	public function asJson() {
		return json_encode($this->asArray());
	}

	public function getIdp() {
		return $this->idp;
	}

	public function setIdp($idp) {
		$this->idp = $idp;
	}

	public function getOpaqueId() {
		return $this->opaque_id;
	}

	public function setOpaqueID($opaque_id) {
		$this->opaque_id = $opaque_id;
	}

	public function getType() {
		return $this->type;
	}

	public function setType($type) {
		$this->type = $type;
	}
}
