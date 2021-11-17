<?php

namespace OCA\ScienceMesh\Share;

use OCP\Share\IShare;
use ScienceMeshSharePermissions;
use ScienceMeshUserId;

class ScienceMeshShare implements IShare {
	private $scienceMeshId;
	private $scienceMeshResourceId;
	private $scienceMeshPermissions;
	private $scienceMeshGrantee;
	private $scienceMeshOwner;
	private $scienceMeshCreator;
	private $scienceMeshCTime;
	private $scienceMeshMTime;
}
