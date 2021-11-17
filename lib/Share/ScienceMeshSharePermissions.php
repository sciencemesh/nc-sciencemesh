<?php

namespace OCA\ScienceMesh\Share;

class ScienceMeshSharePermissions {
	private $add_grant = false;
	private $create_container = false;
	private $delete = false;
	private $get_path = false;
	private $get_quota = false;
	private $initiate_file_download = false;
	private $initiate_file_upload = false;
	private $list_grants = false;
	private $list_container = false;
	private $list_file_versions = false;
	private $list_recycle = false;
	private $move = false;
	private $remove_grant = false;
	private $purge_recycle = false;
	private $restore_file_version = false;
	private $restore_recycle_item = false;
	private $stat = false;
	private $update_grant = false;
	private $deny_grant = false;
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
			$res['permissions'][$key] = $this->$key;
		}
		return $res;
	}

	public function getJson() {
		return json_encode($this->getArray());
	}

	public function setPermission($key, $value) {
		if (in_array($key, ScienceMeshSharePermissions::FIELDS)) {
			if (is_bool($value)) {
				$this->$key = $value;
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
			return $this->$key;
		} else {
			throw new \UnexpectedValueException(
				__CLASS__ .
				"->getPermission: Unknown Permission Type: " .
				$key);
		}
	}
}
