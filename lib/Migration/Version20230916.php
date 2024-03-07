<?php
/**
 * ScienceMesh Nextcloud plugin application.
 *
 * @copyright 2020 - 2024, ScienceMesh.
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.io>
 *
 * @license AGPL-3.0
 *
 *  This code is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License, version 3,
 *  as published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License, version 3,
 *  along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

declare(strict_types=1);

namespace OCA\ScienceMesh\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version20230916 extends SimpleMigrationStep
{

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
	{
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$prefix = $options["tablePrefix"];

		// ocm_received_shares table.
		if (!$schema->hasTable("{$prefix}sm_ocm_rx_sh")) {
			$table = $schema->createTable("{$prefix}sm_ocm_rx_sh");

			$table->addColumn("id", "bigint", [
				"autoincrement" => true,
				"unsigned" => true,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addColumn("share_external_id", "bigint", [
				"unsigned" => false,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addColumn("name", "string", [
				"length" => 255,
				"notnull" => true,
				"comment" => "Original name on the remote server"
			]);

			$table->addColumn("share_with", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("owner", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("initiator", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("ctime", "bigint", [
				"unsigned" => false,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addColumn("mtime", "bigint", [
				"unsigned" => false,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addColumn("expiration", "bigint", [
				"unsigned" => false,
				"notnull" => false,
				"default" => null,
				"length" => 11,
			]);

			$table->addColumn("remote_share_id", "string", [
				"length" => 255,
				"notnull" => false,
				"default" => null,
				"comment" => "share ID at the remote server"
			]);

			$table->setPrimaryKey(["id"]);

			$table->addUniqueIndex(
				["share_external_id"],
				"sm_ocm_rx_ex_id_idx"
			);
			$table->addUniqueIndex(
				["share_with"],
				"sm_ocm_rx_sh_w_idx"
			);
		}

		// ocm_protocol_transfer table.
		if (!$schema->hasTable("{$prefix}sm_ocm_rx_sh_pro_tx")) {
			$table = $schema->createTable("{$prefix}sm_ocm_rx_sh_pro_tx");

			$table->addColumn("ocm_received_share_id", "bigint", [
				"unsigned" => true,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addColumn("source_uri", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("shared_secret", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("size", "bigint", [
				"unsigned" => false,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addUniqueIndex(
				["ocm_received_share_id"],
				"sm_ocm_rx_share_id_tx_idx"
			);
		}

		// ocm_protocol_webapp table.
		if (!$schema->hasTable("{$prefix}sm_ocm_rx_sh_pro_wa")) {
			$table = $schema->createTable("{$prefix}sm_ocm_rx_sh_pro_wa");

			$table->addColumn("ocm_received_share_id", "bigint", [
				"unsigned" => true,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addColumn("uri_template", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("view_mode", "bigint", [
				"unsigned" => false,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addUniqueIndex(
				["ocm_received_share_id"],
				"sm_ocm_rx_share_id_app_idx"
			);
		}

		// ocm_protocol_webdav table.
		if (!$schema->hasTable("{$prefix}sm_ocm_rx_sh_pro_wd")) {
			$table = $schema->createTable("{$prefix}sm_ocm_rx_sh_pro_wd");

			$table->addColumn("ocm_received_share_id", "bigint", [
				"unsigned" => true,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addColumn("uri", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("shared_secret", "string", [
				"length" => 255,
				"notnull" => true,
			]);

			$table->addColumn("permissions", "bigint", [
				"unsigned" => false,
				"notnull" => true,
				"length" => 11,
			]);

			$table->addUniqueIndex(
				["ocm_received_share_id"],
				"sm_ocm_rx_share_id_dav_idx"
			);
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options)
	{
	}
}
