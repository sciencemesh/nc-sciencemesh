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
class Version20230916 implements ISchemaMigration
{
    public function changeSchema(Schema $schema, array $options)
    {
        $prefix = $options['tablePrefix'];

        // ocm_received_shares table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_received_shares")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_received_shares");

            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'unsigned' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('share_external_id', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('name', 'string', [
                'length' => 255,
                'notnull' => true,
                'comment' => 'Original name on the remote server'
            ]);

            $table->addColumn('item_type', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 4,
            ]);

            $table->addColumn('share_with', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('owner', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('initiator', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('ctime', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('mtime', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('expiration', 'bigint', [
                'unsigned' => false,
                'notnull' => false,
                'default' => null,
                'length' => 11,
            ]);

            $table->addColumn('type', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 4,
            ]);

            $table->addColumn('state', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 4,
            ]);

            $table->addColumn('remote_share_id', 'string', [
                'length' => 255,
                'notnull' => false,
                'default' => null,
                'comment' => 'share ID at the remote server'
            ]);

            $table->setPrimaryKey(['id']);

            $table->addUniqueIndex(
                ['share_external_id'],
                'sm_ocm_rx_ex_id_idx'
            );
            $table->addUniqueIndex(
                ['share_with'],
                'sm_ocm_rx_sh_w_idx'
            );
        }

        // ocm_received_share_protocols table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_received_share_protocols")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_received_share_protocols");

            $table->addColumn('id', 'bigint', [
                'autoincrement' => true,
                'unsigned' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('ocm_received_share_id', 'bigint', [
                'unsigned' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('type', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 4,
            ]);

            $table->setPrimaryKey(['id']);

            $table->addUniqueIndex(
                ['ocm_received_share_id'],
                'sm_ocm_rx_pros_rx_sh_id_idx'
            );
        }

        // ocm_protocol_transfer table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_protocol_transfer")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_protocol_transfer");

            $table->addColumn('ocm_protocol_id', 'bigint', [
                'unsigned' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('source_uri', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('shared_secret', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('size', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addUniqueIndex(
                ['ocm_protocol_id'],
                'sm_ocm_pro_tx_pros_id_idx'
            );
        }

        // ocm_protocol_webapp table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_protocol_webapp")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_protocol_webapp");

            $table->addColumn('ocm_protocol_id', 'bigint', [
                'unsigned' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('uri_template', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('view_mode', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addUniqueIndex(
                ['ocm_protocol_id'],
                'sm_ocm_pro_app_pros_id_idx'
            );
        }

        // ocm_protocol_webdav table.
        if (!$schema->hasTable("{$prefix}sciencemesh_ocm_protocol_webdav")) {
            $table = $schema->createTable("{$prefix}sciencemesh_ocm_protocol_webdav");

            $table->addColumn('ocm_protocol_id', 'bigint', [
                'unsigned' => true,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addColumn('uri', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('shared_secret', 'string', [
                'length' => 255,
                'notnull' => true,
            ]);

            $table->addColumn('permissions', 'bigint', [
                'unsigned' => false,
                'notnull' => true,
                'length' => 11,
            ]);

            $table->addUniqueIndex(
                ['ocm_protocol_id'],
                'sm_ocm_pro_dav_pros_id_idx'
            );
        }
    }
}
