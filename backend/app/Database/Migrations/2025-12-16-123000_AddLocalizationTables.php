<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLocalizationTables extends Migration
{
    public function up()
    {
        // Języki
        $this->forge->addField([
            'code'       => ['type' => 'VARCHAR', 'constraint' => 10],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('code', true);
        $this->forge->createTable('locales', true);

        // Helper do translacji (funkcja pomocnicza)
        $createTranslation = function (string $table, string $foreign, string $fkTable) {
            $fields = [
                'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                $foreign        => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'locale'        => ['type' => 'VARCHAR', 'constraint' => 10],
                'name'          => ['type' => 'VARCHAR', 'constraint' => 255],
                'description'   => ['type' => 'TEXT', 'null' => true],
                'metadata'      => ['type' => 'JSON', 'null' => true],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ];

            $this->forge->addField($fields);
            $this->forge->addKey('id', true);
            $this->forge->addKey([$foreign, 'locale'], false, true);
            $this->forge->addForeignKey($foreign, $fkTable, 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('locale', 'locales', 'code', 'CASCADE', 'CASCADE');
            $this->forge->createTable($table, true);
        };

        $createTranslation('game_definition_translations', 'definition_id', 'game_definitions');
        $createTranslation('item_translations', 'item_id', 'items');
        $createTranslation('item_class_translations', 'item_class_id', 'item_classes');
        $createTranslation('item_bundle_translations', 'bundle_id', 'item_bundles');
        $createTranslation('combat_action_translations', 'combat_action_id', 'combat_actions');
        $createTranslation('weapon_trait_translations', 'weapon_trait_id', 'weapon_traits');
        $createTranslation('insanity_translations', 'insanity_id', 'insanities');
        $createTranslation('magic_path_translations', 'magic_path_id', 'magic_paths');
    }

    public function down()
    {
        $tables = [
            'magic_path_translations',
            'insanity_translations',
            'weapon_trait_translations',
            'combat_action_translations',
            'item_bundle_translations',
            'item_class_translations',
            'item_translations',
            'game_definition_translations',
            'locales',
        ];

        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
