<?php

namespace OCA\ScienceMesh\Tests\Unit\Controller;

use PHPUnit_Framework_TestCase;
use OCA\ScienceMesh\NextCloudAdapter;

class NextcloudAdapterTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->config = $this->getMockBuilder('League\Flysystem\Config')->getMock();
		$this->folder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->node = $this->getMockBuilder('OCP\Files\Node')->getMock();
		$this->directory = new NextCloudAdapter($this->folder);
	}

	public function testCopy() {
		$this->folder
			->expects($this->once())
			->method('get')
			->with($this->equalTo('test1'))
			->willReturn($this->node);

		$this->node
			->expects($this->once())
			->method('copy')
			->with($this->equalTo('test2'))
			->willReturn(true);
		$result = $this->directory->copy('test1', 'test2');

		$this->assertEquals(true, $result);
	}

	public function testCreateDir() {
		$this->folder
			->expects($this->once())
			->method('newFolder')
			->with($this->equalTo('someDirName'));
		
		$result = $this->directory->createDir('someDirName', $this->config);

		$this->assertEquals([ 'path' => 'someDirName', 'type' => 'dir' ], $result);
	}

	public function testDelete() {
		$this->folder
			->expects($this->once())
			->method('get')
			->with($this->equalTo('somePath'))
			->willReturn($this->node);

		$this->node
			->expects($this->once())
			->method('delete');

		$result = $this->directory->delete('somePath');

		$this->assertEquals(true, $result);
	}

	public function testDeleteDirTypeFolder() {
		$this->folder
			->expects($this->once())
			->method('get')
			->with($this->equalTo('someDir'))
			->willReturn($this->node);

		$this->node
			->expects($this->once())
			->method('getType')
			->willReturn(\OCP\Files\FileInfo::TYPE_FOLDER);

		$this->node
			->expects($this->once())
			->method('delete');

		$result = $this->directory->deleteDir('someDir');

		$this->assertEquals(true, $result);
	}

	public function testDeleteDirTypeFile() {
		$this->folder
			->expects($this->once())
			->method('get')
			->with($this->equalTo('someDir'))
			->willReturn($this->node);
		
		$this->node
			->expects($this->once())
			->method('getType')
			->willReturn(\OCP\Files\FileInfo::TYPE_FILE);

		$result = $this->directory->deleteDir('someDir');

		$this->assertEquals(false, $result);
	}

	public function testDeleteDirNotFound() {
		$this->folder
			->expects($this->once())
			->method('get')
			->with($this->equalTo('someDir'))
			->will($this->throwException(new \OCP\Files\NotFoundException()));
	
		$result = $this->directory->deleteDir('someDir');

		$this->assertEquals(false, $result);
	}

}
