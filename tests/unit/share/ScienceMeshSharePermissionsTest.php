<?php

namespace OCA\ScienceMesh\Tests\Unit\Share;

use PHPUnit_Framework_TestCase;

use OCA\ScienceMesh\Share\ScienceMeshSharePermissions;

class ScienceMeshSharePermissionsTest extends PHPUnit_Framework_TestCase {
	public $json_false = '{
			"permissions": {
				"add_grant": false,
				"create_container": false,
				"delete": false,
				"get_path": false,
				"get_quota": false,
				"initiate_file_download": false,
				"initiate_file_upload": false,
				"list_grants": false,
				"list_container": false,
				"list_file_versions": false,
				"list_recycle": false,
				"move": false,
				"remove_grant": false,
				"purge_recycle": false,
				"restore_file_version": false,
				"restore_recycle_item": false,
				"stat": false,
				"update_grant": false,
				"deny_grant": false	
			}
		}';
	public $array_false = [
		"permissions" => [
			"add_grant" => false,
			"create_container" => false,
			"delete" => false,
			"get_path" => false,
			"get_quota" => false,
			"initiate_file_download" => false,
			"initiate_file_upload" => false,
			"list_grants" => false,
			"list_container" => false,
			"list_file_versions" => false,
			"list_recycle" => false,
			"move" => false,
			"remove_grant" => false,
			"purge_recycle" => false,
			"restore_file_version" => false,
			"restore_recycle_item" => false,
			"stat" => false,
			"update_grant" => false,
			"deny_grant" => false
		]
	];
	public $json_true = '{
			"add_grant": true,
			"create_container": true,
			"delete": true,
			"get_path": true,
			"get_quota": true,
			"initiate_file_download": true,
			"initiate_file_upload": true,
			"list_grants": true,
			"list_container": true,
			"list_file_versions": true,
			"list_recycle": true,
			"move": true,
			"remove_grant": true,
			"purge_recycle": true,
			"restore_file_version": true,
			"restore_recycle_item": true,
			"stat": true,
			"update_grant": true,
			"deny_grant": true	
		}';
	public $json_malformed = '{
		"permissions": {
			"add_grant": true,';

	public function testFromJson() {
		$permissions_expected = new ScienceMeshSharePermissions;
		$permissions_from_json_false = ScienceMeshSharePermissions::fromJson($this->json_false);
		$this->assertEquals($permissions_expected, $permissions_from_json_false);
		foreach (ScienceMeshSharePermissions::FIELDS as $field) {
			$permissions_expected->setPermission($field, true);
		}
		$permissions_from_json_true = ScienceMeshSharePermissions::fromJson($this->json_true);
		$this->assertEquals($permissions_expected, $permissions_from_json_true);
		$this->expectException(\InvalidArgumentException::class);
		$permissions_fail = ScienceMeshSharePermissions::fromJson($this->json_malformed);
	}
	public function testGetArray() {
		$permissions = new ScienceMeshSharePermissions;
		$this->assertEquals($permissions->getArray(), $this->array_false);
	}
	public function testGetJson() {
		$permissions = new ScienceMeshSharePermissions;
		$this->assertEquals(json_decode($permissions->getJson()), json_decode($this->json_false));
	}
	public function testPermission() {
		$permissions = new ScienceMeshSharePermissions;
		$add_grant = $permissions->getPermission('add_grant');
		$this->assertEquals($add_grant, false);
		$permissions->setPermission('add_grant', true);
		$add_grant = $permissions->getPermission('add_grant');
		$this->assertEquals($add_grant, true);
	}
	public function testSetPermissionValueException() {
		$permissions = new ScienceMeshSharePermissions;
		$this->expectException(\InvalidArgumentException::class);
		$permissions->setPermission('add_grant', 0);
	}
	public function testSetPermissionKeyException() {
		$permissions = new ScienceMeshSharePermissions;
		$this->expectException(\UnexpectedValueException::class);
		$permissions->setPermission('notarealpermissiontype', true);
	}
	public function testGetPermissionKeyException() {
		$permissions = new ScienceMeshSharePermissions;
		$this->expectException(\UnexpectedValueException::class);
		$permissions->getPermission('notarealpermissiontype');
	}
}
