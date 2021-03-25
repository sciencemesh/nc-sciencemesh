<?php

declare(strict_types=1);

namespace OCA\ScienceMesh\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use Doctrine\DBAL\Types\Type;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version010000Date20210118175358 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('sciencemesh')) {
			$table = $schema->createTable("sciencemesh");
			$table->addColumn('apikey', 'string', [
				'notnull' => true,
			]);
			$table->addColumn('sitename', 'string', [
				'notnull' => true,
			]);
			$table->addColumn('siteurl', 'string', [
				'notnull' => true,
			]);
			$table->addColumn('country', 'string', [
				'notnull' => true,
				'length' => 3,
			]);
			$table->addColumn('iopurl', 'string', [
				'notnull' => true,
			]);
			$table->addColumn('numusers', Type::BIGINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('numfiles', Type::BIGINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('numstorage', Type::BIGINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
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
