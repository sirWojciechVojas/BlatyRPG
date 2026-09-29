<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCampaignCalendars extends Migration
{
    public function up()
    {
        $this->createStates();
        $this->createEvents();
        $this->createParticipants();
        $this->createMoonOverrides();
    }

    public function down()
    {
        $this->forge->dropTable('campaign_calendar_moon_overrides', true);
        $this->forge->dropTable('campaign_calendar_event_participants', true);
        $this->forge->dropTable('campaign_calendar_events', true);
        $this->forge->dropTable('campaign_calendars', true);
    }

    private function createStates(): void
    {
        if ($this->db->tableExists('campaign_calendars')) {
            return;
        }
        $this->forge->addField([
            'campaign_id' => $this->unsignedInt(),
            'calendar_key' => ['type' => 'VARCHAR', 'constraint' => 64],
            'year' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'day_of_year' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'minute_of_day' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'is_running' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0],
            'revision' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'default' => 1],
            'updated_by_user_id' => $this->nullableUnsignedInt(),
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('campaign_id', true);
        $this->forge->addKey(['calendar_key', 'year']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('campaign_calendars');
    }

    private function createEvents(): void
    {
        if ($this->db->tableExists('campaign_calendar_events')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->idField(),
            'campaign_id' => $this->unsignedInt(),
            'title' => ['type' => 'VARCHAR', 'constraint' => 180],
            'description' => ['type' => 'TEXT', 'null' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'start_year' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'start_day_of_year' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'start_minute' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'end_year' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'end_day_of_year' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'end_minute' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'color' => ['type' => 'CHAR', 'constraint' => 7, 'default' => '#7b5b38'],
            'visibility' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'all'],
            'all_day' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 1],
            'repeat_yearly' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_by_user_id' => $this->unsignedInt(),
            'updated_by_user_id' => $this->unsignedInt(),
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'start_year', 'start_day_of_year', 'start_minute']);
        $this->forge->addKey(['campaign_id', 'end_year', 'end_day_of_year', 'end_minute']);
        $this->forge->addKey(['campaign_id', 'visibility', 'start_year']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('campaign_calendar_events');
    }

    private function createParticipants(): void
    {
        if ($this->db->tableExists('campaign_calendar_event_participants')) {
            return;
        }
        $this->forge->addField([
            'event_id' => $this->unsignedInt(),
            'user_id' => $this->unsignedInt(),
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey(['event_id', 'user_id'], true);
        $this->forge->addKey(['user_id', 'event_id']);
        $this->forge->addForeignKey('event_id', 'campaign_calendar_events', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('campaign_calendar_event_participants');
    }

    private function createMoonOverrides(): void
    {
        if ($this->db->tableExists('campaign_calendar_moon_overrides')) {
            return;
        }
        $this->forge->addField([
            'id' => $this->idField(),
            'campaign_id' => $this->unsignedInt(),
            'year' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'day_of_year' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true],
            'phase_key' => ['type' => 'VARCHAR', 'constraint' => 32],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'updated_by_user_id' => $this->unsignedInt(),
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'year', 'day_of_year']);
        $this->forge->addKey(['campaign_id', 'year', 'day_of_year', 'phase_key']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('campaign_calendar_moon_overrides');
    }

    private function idField(): array
    {
        return [
            'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
            'auto_increment' => true,
        ];
    }

    private function unsignedInt(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true];
    }

    private function nullableUnsignedInt(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
    }
}
