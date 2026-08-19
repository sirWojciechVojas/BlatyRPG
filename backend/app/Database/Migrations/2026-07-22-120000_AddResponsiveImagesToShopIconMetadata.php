<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddResponsiveImagesToShopIconMetadata extends Migration
{
    public function up()
    {
        $this->forge->addColumn('shop_icon_metadata', [
            'image_path_small' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
                'after' => 'image_path',
            ],
            'image_path_large' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
                'after' => 'image_path_small',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('shop_icon_metadata', 'image_path_large');
        $this->forge->dropColumn('shop_icon_metadata', 'image_path_small');
    }
}
