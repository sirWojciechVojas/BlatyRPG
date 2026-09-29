<?php

namespace App\Database\Migrations;

use App\Services\Shop\LegacyCharacterInventoryImporter;
use CodeIgniter\Database\Migration;

class ImportLegacyCharacterInventories extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('legacy_character_inventory_imports')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                    'auto_increment' => true,
                ],
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'character_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'legacy_equipment_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'legacy_inventory_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'template_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'instance_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['campaign_id', 'legacy_inventory_id']);
            $this->forge->addKey(['campaign_id', 'legacy_equipment_id']);
            $this->forge->addKey(['campaign_id', 'character_id']);
            $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('template_id', 'shop_templates', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('instance_id', 'shop_item_instances', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('legacy_character_inventory_imports', true);
        }
        (new LegacyCharacterInventoryImporter($this->db))->import();
    }

    public function down()
    {
        (new LegacyCharacterInventoryImporter($this->db))->remove();
        $this->forge->dropTable('legacy_character_inventory_imports', true);
    }
}
