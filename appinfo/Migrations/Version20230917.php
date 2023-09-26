<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\Migrations;

use Doctrine\DBAL\Schema\Schema;
use OCP\Migration\ISchemaMigration;

/** Creates initial schema */
class Version20230917 implements ISchemaMigration
{
    public function changeSchema(Schema $schema, array $options)
    {
        $prefix = $options["tablePrefix"];

        // ocm_sent_shares table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_sent_shares")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_sent_shares");

            $table->addColumn("id", "bigint", [
                "autoincrement" => true,
                "unsigned" => true,
                "notnull" => true,
                "length" => 11,
            ]);

            $table->addColumn("share_internal_id", "bigint", [
                "unsigned" => false,
                "notnull" => true,
                "length" => 11,
            ]);

            $table->addColumn("name", "string", [
                "length" => 255,
                "notnull" => true,
                "comment" => "Original name on the sending server"
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

            $table->setPrimaryKey(["id"]);

            $table->addUniqueIndex(
                ["share_internal_id"],
                "sm_ocm_tx_in_id_idx"
            );
            $table->addUniqueIndex(
                ["share_with"],
                "sm_ocm_rx_sh_w_idx"
            );
        }

        // ocm_protocol_transfer table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_sent_share_protocol_transfer")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_sent_share_protocol_transfer");

            $table->addColumn("ocm_sent_share_id", "bigint", [
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
                ["ocm_sent_share_id"],
                "sm_ocm_tx_share_id_tx_idx"
            );
        }

        // ocm_protocol_webapp table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_sent_share_protocol_webapp")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_sent_share_protocol_webapp");

            $table->addColumn("ocm_sent_share_id", "bigint", [
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
                ["ocm_sent_share_id"],
                "sm_ocm_tx_share_id_app_idx"
            );
        }

        // ocm_protocol_webdav table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_sent_share_protocol_webdav")) {
            $table = $schema->createTable("{$prefix}sciencemesh_sent_share_ocm_protocol_webdav");

            $table->addColumn("ocm_sent_share_id", "bigint", [
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
                ["ocm_sent_share_id"],
                "sm_ocm_tx_share_id_dav_idx"
            );
        }
    }
}
