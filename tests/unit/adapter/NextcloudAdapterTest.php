<?php

namespace OCA\ScienceMesh\Tests\Unit\Controller;

use PHPUnit_Framework_TestCase;
use OCA\ScienceMesh\NextcloudAdapter;

class NextcloudAdapterTest extends PHPUnit_Framework_TestCase {
	public function setUp() {
		$this->config = $this->getMockBuilder('League\Flysystem\Config')->getMock();
		$this->folder = $this->getMockBuilder('OCP\Files\Folder')->getMock();
		$this->node = $this->getMockBuilder('OCP\Files\Node')->getMock();
		$this->directory = new NextcloudAdapter($this->folder);
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
	
	private function getMetadataSetup() {
		$this->folder
			->expects($this->once())
			->method('get')
			->with($this->equalTo('some/path/to/file'))
			->willReturn($this->node);

		$this->folder
			->expects($this->once())
			->method('getPath')
			->willReturn('some/path/to');
		
		$this->node
			->expects($this->exactly(2))
			->method('getType')
			->willReturn(\OCP\Files\FileInfo::TYPE_FILE);

		$this->node
			->expects($this->exactly(2))
			->method('getPath')
			->willReturn('some/path/to/file');

		$this->node
			->expects($this->once())
			->method('getSize')
			->willReturn(1234);

		$this->node
			->expects($this->once())
			->method('getMtime')
			->willReturn(1234567890123);

		$this->node
			->expects($this->once())
			->method('getMimeType')
			->willReturn('text/plain');
	}

	public function testGetMetadataFound() {
		$this->getMetadataSetup();

		$result = $this->directory->getMetadata('some/path/to/file');

		$this->assertEquals([
			'mimetype' => 'text/plain',
			'path' => 'file',
			'size' => 1234,
			'basename' => 'file',
			'timestamp' => 1234567890123,
			'type' => 'file',
			'visibility' => 'public',
		], $result);
	}

	public function testGetMetadataNotFound() {
		$this->folder
			->expects($this->once())
			->method('get')
			->with($this->equalTo('some/path/to/file'))
			->will($this->throwException(new \OCP\Files\NotFoundException()));

		$result = $this->directory->getMetadata('some/path/to/file');

		$this->assertEquals(false, $result);
	}

	public function testGetMimeType() {
		$this->getMetadataSetup();

		$result = $this->directory->getMimeType('some/path/to/file');

		$this->assertEquals([
			'mimetype' => 'text/plain',
			'path' => 'file',
			'size' => 1234,
			'basename' => 'file',
			'timestamp' => 1234567890123,
			'type' => 'file',
			'visibility' => 'public',
		], $result);
	}

	public function testSize() {
		$this->getMetadataSetup();

		$result = $this->directory->getSize('some/path/to/file');

		$this->assertEquals([
			'mimetype' => 'text/plain',
			'path' => 'file',
			'size' => 1234,
			'basename' => 'file',
			'timestamp' => 1234567890123,
			'type' => 'file',
			'visibility' => 'public',
		], $result);
	}

	public function testGetTimestamp() {
		$this->getMetadataSetup();

		$result = $this->directory->getTimestamp('some/path/to/file');

		$this->assertEquals([
			'mimetype' => 'text/plain',
			'path' => 'file',
			'size' => 1234,
			'basename' => 'file',
			'timestamp' => 1234567890123,
			'type' => 'file',
			'visibility' => 'public',
		], $result);
	}
	public function testGetVisibility() {
		$this->getMetadataSetup();

		$result = $this->directory->getVisibility('some/path/to/file');

		$this->assertEquals([
			'mimetype' => 'text/plain',
			'path' => 'file',
			'size' => 1234,
			'basename' => 'file',
			'timestamp' => 1234567890123,
			'type' => 'file',
			'visibility' => 'public',
		], $result);
	}

	public function testHas() {
		$this->folder
			->expects($this->once())
			->method('nodeExists')
			->with($this->equalTo('test1'))
			->willReturn($this->node);

		
		$result = $this->directory->has('test1');

		$this->assertEquals($this->node, $result);
	}
}
