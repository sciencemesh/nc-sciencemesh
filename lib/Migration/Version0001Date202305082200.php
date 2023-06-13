<?php

declare(strict_types=1);

namespace OCA\ScienceMesh\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version0001Date202305082200 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('ocm_tokens')) {
			$table = $schema->createTable("ocm_tokens");
			$table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('token', 'string', ['length' => 255, 'notnull' => true]);
			$table->addColumn('initiator', 'string', ['length' => 255, 'notnull' => true]);
			$table->addColumn('expiration', 'string', ['length' => 255, 'notnull' => true]);
			$table->addColumn('description', 'string', ['length' => 255, 'notnull' => true]);
			$table->setPrimaryKey(['id']);
		}

		
		if (!$schema->hasTable('ocm_remote_users')) {
			$table1 = $schema->createTable("ocm_remote_users");
			$table1->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
			$table1->addColumn('opaque_user_id', 'string', ['length' => 255, 'notnull' => true]);
			$table1->addColumn('idp', 'string', ['length' => 255, 'notnull' => true]);
			$table1->addColumn('email', 'string', ['length' => 255, 'notnull' => true]);
			$table1->addColumn('initiator', 'string', ['length' => 255, 'notnull' => true]);
			$table1->addColumn('display_name', 'string', ['length' => 255, 'notnull' => true]);
			$table1->setPrimaryKey(['id']);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
	}
}
