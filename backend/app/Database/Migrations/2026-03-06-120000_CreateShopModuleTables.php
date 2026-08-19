<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShopModuleTables extends Migration
{
    public function up()
    {
        // shop_templates
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'null' => true],
            'details'     => ['type' => 'TEXT', 'null' => true],
            'item_class'  => ['type' => 'VARCHAR', 'constraint' => 64],
            'item_id'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'item_genre'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'img_class'   => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'v0001'],
            'prize'       => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'charge'      => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'draft'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'weapon_json' => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'item_class']);
        $this->forge->addKey(['campaign_id', 'deleted_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_templates', true);

        // shops
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'owner_code'  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'BG1'],
            'owner_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'is_active']);
        $this->forge->addKey(['campaign_id', 'deleted_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shops', true);

        // shop_profiles
        $this->forge->addField([
            'id'                      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'             => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'shop_id'                 => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'type_id'                 => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'signboard_name'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'owner_code'              => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'BG1'],
            'owner_name'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'signboard_alt_names_json'=> ['type' => 'JSON', 'null' => true],
            'category_tags_json'      => ['type' => 'JSON', 'null' => true],
            'world_profile_id'        => ['type' => 'VARCHAR', 'constraint' => 128, 'default' => 'standard'],
            'location_type'           => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'miasto'],
            'legal_status'            => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'legal'],
            'wealth_tier'             => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'standard'],
            'reputation'              => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'neutralna'],
            'seasonality'             => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'caloroczny'],
            'counterfeit_risk'        => ['type' => 'INT', 'constraint' => 3, 'default' => 10],
            'created_at'              => ['type' => 'DATETIME', 'null' => true],
            'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'shop_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('shop_id', 'shops', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_profiles', true);

        // shop_containers
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'shop_id'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'container_type'=> ['type' => 'VARCHAR', 'constraint' => 32],
            'system_key'    => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'owner_code'    => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'capacity'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'is_active'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'container_type']);
        $this->forge->addKey(['campaign_id', 'owner_code']);
        $this->forge->addKey(['campaign_id', 'shop_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('shop_id', 'shops', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_containers', true);

        // shop_container_template_items
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'container_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'template_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'quantity'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'price_override'=> ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['container_id', 'template_id']);
        $this->forge->addKey(['campaign_id', 'container_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('container_id', 'shop_containers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('template_id', 'shop_templates', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_container_template_items', true);

        // shop_item_instances
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'template_id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name_override'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'data_override_json'=> ['type' => 'JSON', 'null' => true],
            'note'             => ['type' => 'TEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'template_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('template_id', 'shop_templates', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_item_instances', true);

        // shop_container_instance_items
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'container_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'instance_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'price_override'=> ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['instance_id']);
        $this->forge->addKey(['campaign_id', 'container_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('container_id', 'shop_containers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('instance_id', 'shop_item_instances', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_container_instance_items', true);

        // shop_owner_wallets
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'owner_code'   => ['type' => 'VARCHAR', 'constraint' => 32],
            'brass_balance'=> ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'owner_code']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_owner_wallets', true);

        // shop_trade_transactions
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'transaction_uuid'=> ['type' => 'VARCHAR', 'constraint' => 64],
            'idempotency_key'=> ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'actor_user_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'owner_code'     => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'shop_id'        => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'transaction_type'=> ['type' => 'VARCHAR', 'constraint' => 32],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'SUCCESS'],
            'error_code'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'total_brass'    => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'payload_json'   => ['type' => 'JSON', 'null' => true],
            'response_json'  => ['type' => 'JSON', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['transaction_uuid']);
        $this->forge->addKey(['campaign_id', 'transaction_type', 'created_at']);
        $this->forge->addKey(['campaign_id', 'idempotency_key']);
        $this->forge->addUniqueKey(['campaign_id', 'transaction_type', 'idempotency_key']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('shop_id', 'shops', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shop_trade_transactions', true);

        // shop_owner_claims
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'user_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'owner_code'  => ['type' => 'VARCHAR', 'constraint' => 32],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'user_id', 'owner_code']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_owner_claims', true);

        // shop_suggestion_cache
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'shop_id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'profile_hash'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'suggestions_json'  => ['type' => 'LONGTEXT', 'null' => true],
            'recommendations_json'=> ['type' => 'LONGTEXT', 'null' => true],
            'generated_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'shop_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('shop_id', 'shops', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_suggestion_cache', true);

        // shop_catalog_nodes
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'node_key'     => ['type' => 'VARCHAR', 'constraint' => 128],
            'parent_key'   => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'level'        => ['type' => 'VARCHAR', 'constraint' => 32],
            'name_pl'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'name_en'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'description_pl'=> ['type' => 'TEXT', 'null' => true],
            'payload_json' => ['type' => 'LONGTEXT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['node_key']);
        $this->forge->addKey(['level', 'parent_key']);
        $this->forge->createTable('shop_catalog_nodes', true);

        // shop_world_profiles
        $this->forge->addField([
            'id'               => ['type' => 'VARCHAR', 'constraint' => 128],
            'label_pl'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'label_en'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'description'      => ['type' => 'TEXT', 'null' => true],
            'impact_summary_pl'=> ['type' => 'TEXT', 'null' => true],
            'modifiers_json'   => ['type' => 'LONGTEXT', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('shop_world_profiles', true);
    }

    public function down()
    {
        $tables = [
            'shop_world_profiles',
            'shop_catalog_nodes',
            'shop_suggestion_cache',
            'shop_owner_claims',
            'shop_trade_transactions',
            'shop_owner_wallets',
            'shop_container_instance_items',
            'shop_item_instances',
            'shop_container_template_items',
            'shop_containers',
            'shop_profiles',
            'shops',
            'shop_templates',
        ];

        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
