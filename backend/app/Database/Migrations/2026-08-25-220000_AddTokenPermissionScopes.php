<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTokenPermissionScopes extends Migration
{
    private const FIELDS = [
        'visible_to_json', 'controlled_by_json',
        'editable_by_json', 'observer_by_json',
    ];

    public function up()
    {
        if (!$this->db->tableExists('scene_tokens')) return;
        foreach (self::FIELDS as $field) {
            if (!$this->db->fieldExists($field, 'scene_tokens')) {
                $this->forge->addColumn('scene_tokens', [
                    $field => ['type' => 'JSON', 'null' => true, 'after' => 'locked'],
                ]);
            }
        }
        $this->backfill();
    }

    public function down()
    {
        if (!$this->db->tableExists('scene_tokens')) return;
        foreach (self::FIELDS as $field) {
            if ($this->db->fieldExists($field, 'scene_tokens')) {
                $this->forge->dropColumn('scene_tokens', $field);
            }
        }
    }

    private function backfill(): void
    {
        $rows = $this->db->table('scene_tokens')
            ->select('id, hidden, visible_to_json, controlled_by_json, '
                . 'editable_by_json, observer_by_json')
            ->get()->getResultArray();
        $table = $this->db->table('scene_tokens');
        foreach ($rows as $row) {
            $scope = static function (string $mode): string {
                return json_encode(['mode' => $mode, 'userIds' => []]);
            };
            $defaults = [
                'visible_to_json' => $scope(!empty($row['hidden']) ? 'gm' : 'everyone'),
                'controlled_by_json' => $scope('inherit'),
                'editable_by_json' => $scope('gm'),
                'observer_by_json' => $scope('inherit'),
            ];
            $changes = array_filter(
                $defaults,
                fn ($value, $field): bool => empty($row[$field]),
                ARRAY_FILTER_USE_BOTH
            );
            if ($changes) $table->where('id', (int) $row['id'])->update($changes);
        }
    }
}
