<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Browser audio device preferences owned by an existing application user. */
class CreateUserAudioDeviceSettings extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('user_audio_device_settings')) {
            return;
        }

        $this->forge->addField([
            'user_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
            ],
            'microphone_device_id' => [
                'type' => 'VARCHAR',
                'constraint' => 512,
                'null' => true,
            ],
            'output_device_id' => [
                'type' => 'VARCHAR',
                'constraint' => 512,
                'null' => true,
            ],
            'external_input_1_device_id' => [
                'type' => 'VARCHAR',
                'constraint' => 512,
                'null' => true,
            ],
            'external_input_2_device_id' => [
                'type' => 'VARCHAR',
                'constraint' => 512,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('user_id', true);
        $this->forge->addForeignKey(
            'user_id',
            'users',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->forge->createTable('user_audio_device_settings');
    }

    public function down()
    {
        $this->forge->dropTable('user_audio_device_settings', true);
    }
}
