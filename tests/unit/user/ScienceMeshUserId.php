<?php

namespace OCA\ScienceMesh\Test\Unit\User;

use PHPUnit_Framework_TestCase;

use OCA\ScienceMesh\User\ScienceMeshUserId;

class ScienceMeshUserIdTest extends PHPUnit_Framework_TestCase {
	public function testFromJson() {
		$json = '{
			"idp": "0.0.0.0:19000",
			"opaque_id": "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
			"type": 1
		}';
		$id = ScienceMeshUserId::fromJson($json);
		$id2 = new ScienceMeshUserId('0.0.0.0:19000','f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c', 1);
		$this->assertEquals($id, $id2);
		$id3 = new ScienceMeshUserId('127.0.0.1:17000', 'deadbeef-face-dave-cafe-cocoaadded1', 2);
		$this->assertNotEquals($id, $id3);
	}
	public function testFromJsonInvalidJson() {
		$json = '{';
		$this->expectException(\InvalidArgumentException::class);
		ScienceMeshUserId::fromJson($json);
	}
	public function testFromJsonNotAScienceMeshUserId() {
		$json = '{
			"x":42
		}';
		$this->expectException(\DomainException::class);
		ScienceMeshUserId::fromJson($json);
	}
	public function testAsJson() {
		$json = '{
			"idp": "0.0.0.0:19000",
			"opaque_id": "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
			"type": 1
		}';
		$id2 = new ScienceMeshUserId('0.0.0.0:19000','f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c', 1);
		$this->assertEquals(json_decode($json), json_decode($id2->asJson()));
	}
	public function testFromArray() {
		$arr = [
			"idp" => "0.0.0.0:19000",
			"opaque_id" => "f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c",
			"type" => 1
		];
		$id = ScienceMeshUserId::fromArray($arr);
		$id2 = new ScienceMeshUserId('0.0.0.0:19000','f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c', 1);
		$this->assertEqual($id, $id2);
	}
	public function testIdp() {
		$id = new ScienceMeshUserId('0.0.0.0:19000','f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c',1);
		$this->assertEquals($id->getIdp(), '0.0.0.0:19000');
		$id->setIdp('127.0.0.1:17000');
		$this->assertEquals($id->getIdp(), '127.0.0.1:17000');
	}
	public function testOpaqueId() {
		$id = new ScienceMeshUserId('0.0.0.0:19000','f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c',1);
		$this->assertEquals($id->getOpaqueId(), 'f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c');
		$id->setOpaqueId('deadbeef-face-dave-cafe-cocoaadded1');
		$this->assertEquals($id->getOpaqueId(), 'deadbeef-face-dave-cafe-cocoaadded1');
	}
	public function testType() {
		$id = new ScienceMeshUserId('0.0.0.0:19000','f7fbf8c8-139b-4376-b307-cf0a8c2d0d9c',1);
		$this->assertEquals($id->getType(), 1);
		$id->setType(2);
		$this->assertEquals($id->getType(), 2);
	}
}
