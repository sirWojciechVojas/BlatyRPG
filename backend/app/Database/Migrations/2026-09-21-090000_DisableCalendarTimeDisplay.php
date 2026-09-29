<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DisableCalendarTimeDisplay extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('campaign_calendars')
            || !$this->db->fieldExists('show_time', 'campaign_calendars')) {
            return;
        }
        $this->forge->modifyColumn('campaign_calendars', [
            'show_time' => [
                'name' => 'show_time',
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 0,
            ],
        ]);
        $this->db->table('campaign_calendars')->update(['show_time' => 0]);
    }

    public function down()
    {
        if (!$this->db->tableExists('campaign_calendars')
            || !$this->db->fieldExists('show_time', 'campaign_calendars')) {
            return;
        }
        $this->forge->modifyColumn('campaign_calendars', [
            'show_time' => [
                'name' => 'show_time',
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 1,
            ],
        ]);
        $this->db->table('campaign_calendars')->update(['show_time' => 1]);
    }
}
