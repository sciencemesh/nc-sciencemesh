<?php

namespace OCA\ScienceMesh\Migration;

use Closure;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20211201101630 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		$schema = $schemaClosure();
		$shares = $schema->createTable('sciencemesh_shares');
		$users = $schema->createTable('sciencemesh_users');
		$shares->addColumn(
			'opaque_id',
			\OCP\DB\Types::STRING,
			['notnull' => true]
		);
		$shares->addColumn(
			'resource_id',
			\OCP\DB\Types::STRING,
			['notnull' => true]
		);
		$shares->addColumn(
			'permissions',
			\OCP\DB\Types::INTEGER,
			[]
		);
		$shares->addColumn(
			'grantee',
			\OCP\DB\Types::INTEGER,
			[]
		);
		$shares->addColumn(
			'owner',
			\OCP\DB\Types::INTEGER,
			[]
		);
		$shares->addColumn(
			'creator',
			\OCP\DB\Types::INTEGER,
			[]
		);
		$shares->addColumn(
			'ctime',
			\OCP\DB\Types::INTEGER,
			['notnull' => true]
		);
		$shares->addColumn(
			'mtime',
			\OCP\DB\Types::INTEGER,
			['notnull' => true]
		);
		$shares->addColumn(
			'is_external',
			\OCP\DB\Types::BOOLEAN,
			['notnull' => false]
		);
		$shares->addColumn(
			'foreign_id',
			\OCP\DB\Types::INTEGER,
			[]
		);
		$shares->setPrimaryKey(['opaque_id']);
		$users->addColumn(
			'id',
			\OCP\DB\Types::INTEGER,
			['notnull' => true, 'autoincrement' => true, 'unsigned' => true]
		);
		$users->addColumn(
			'idp',
			\OCP\DB\Types::STRING,
			['notnull' => true]
		);
		$users->addColumn(
			'opaque_id',
			\OCP\DB\Types::STRING,
			['notnull' => true]
		);
		$users->addColumn(
			'type',
			\OCP\DB\Types::INTEGER,
			[]
		);
		$users->setPrimaryKey(['id']);
		return $schema;
	}
}
