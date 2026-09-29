<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCalendarTimeVisibility extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('campaign_calendars')
            || $this->db->fieldExists('show_time', 'campaign_calendars')) {
            return;
        }
        $this->forge->addColumn('campaign_calendars', [
            'show_time' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'unsigned' => true,
                'default' => 1,
                'after' => 'minute_of_day',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('campaign_calendars')
            && $this->db->fieldExists('show_time', 'campaign_calendars')) {
            $this->forge->dropColumn('campaign_calendars', 'show_time');
        }
    }
}
