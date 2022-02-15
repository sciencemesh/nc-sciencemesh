<?php

namespace OCA\ScienceMesh\Share;

class ScienceMeshSharePermissions {
	private $ADD_GRANT = 1 << 0;
	private $CREATE_CONTAINER = 1 << 1;
	private $DELETE = 1 << 2;
	private $GET_PATH = 1 << 3;
	private $GET_QUOTA = 1 << 4;
	private $INITIATE_FILE_DOWNLOAD = 1 << 5;
	private $INITIATE_FILE_UPLOAD = 1 << 6;
	private $LIST_GRANTS = 1 << 7;
	private $LIST_CONTAINER = 1 << 8;
	private $LIST_FILE_VERSIONS = 1 << 9;
	private $LIST_RECYCLE = 1 << 10;
	private $MOVE = 1 << 11;
	private $REMOVE_GRANT = 1 << 12;
	private $PURGE_RECYCLE = 1 << 13;
	private $RESTORE_FILE_VERSION = 1 << 14;
	private $RESTORE_RECYCLE_ITEM = 1 << 15;
	private $STAT = 1 << 16;
	private $UPDATE_GRANT = 1 << 17;
	private $DENY_GRANT = 1 << 18;
	public const FIELDS = [
		'add_grant',
		'create_container',
		'delete',
		'get_path',
		'get_quota',
		'initiate_file_download',
		'initiate_file_upload',
		'list_grants',
		'list_container',
		'list_file_versions',
		'list_recycle',
		'move',
		'remove_grant',
		'purge_recycle',
		'restore_file_version',
		'restore_recycle_item',
		'stat',
		'update_grant',
		'deny_grant',
	];

	private $val = 0;

	public static function fromJson($json) {
		$permission_array = json_decode($json, true);
		if (isset($permission_array['permissions']) && is_array($permission_array['permissions'])) {
			$permission_array = $permission_array['permissions'];
		}
		if ($permission_array === null) {
			throw new \InvalidArgumentException(
				__CLASS__ .
				': Failed to parse JSON. $json: ' .
				$json .
				' json_last_error CODE: ' .
				json_last_error());
		}
		$permissions = new ScienceMeshSharePermissions;
		foreach (ScienceMeshSharePermissions::FIELDS as $key) {
			if (isset($permission_array[$key]) && is_bool($permission_array[$key])) {
				$permissions->setPermission($key, $permission_array[$key]);
			}
		}
		return $permissions;
	}

	public function getArray() {
		$res = [
			'permissions' => []
		];
		foreach (ScienceMeshSharePermissions::FIELDS as $key) {
			$k = strtoupper($key);
			$res['permissions'][$key] = (bool)$this->val & $this->$k;
		}
		return $res;
	}

	public function getJson() {
		return json_encode($this->getArray());
	}

	public function getCode() {
		return $this->val;
	}

	public function setPermission($key, $value) {
		if (in_array($key, ScienceMeshSharePermissions::FIELDS)) {
			if (is_bool($value)) {
				if ($this->getPermission($key) == $value) {
					return;
				} else {
					$k = strtoupper($key);
					$value?$this->val += $this->$k:$this->val -= $this->$k;
				}
			} else {
				throw new \InvalidArgumentException(
					__CLASS__ .
					": ScienceMesh Permission values have to be booleans.");
			}
		} else {
			throw new \UnexpectedValueException(
				__CLASS__ .
				"->setPermission: Unknown Permission Type: " .
				$key);
		}
	}

	public function getPermission($key) {
		if (in_array($key, ScienceMeshSharePermissions::FIELDS)) {
			$k = strtoupper($key);
			return (bool)$this->val & $this->$k;
		} else {
			throw new \UnexpectedValueException(
				__CLASS__ .
				"->getPermission: Unknown Permission Type: " .
				$key);
		}
	}
}
