<?php

namespace App\Database\Migrations;

use App\Services\Token\TokenGridPositionService;
use CodeIgniter\Database\Migration;

class SnapExistingTokensToGrid extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scene_tokens') || !$this->db->tableExists('scenes')) {
            return;
        }
        $rows = $this->db->table('scene_tokens token')
            ->select([
                'token.id', 'token.x', 'token.y', 'token.width', 'token.height',
                'token.revision', 'scene.grid_type', 'scene.grid_size',
                'scene.grid_offset_x', 'scene.grid_offset_y',
            ])
            ->join('scenes scene', 'scene.id = token.scene_id')
            ->where('token.deleted_at', null)
            ->where('scene.grid_type !=', 'gridless')
            ->get()->getResultArray();
        $snapper = new TokenGridPositionService();
        foreach ($rows as $row) {
            $position = $snapper->snap(
                $row,
                ['x' => $row['x'], 'y' => $row['y']],
                (float) $row['width'],
                (float) $row['height']
            );
            if ($this->samePosition($row, $position)) continue;
            $this->db->table('scene_tokens')
                ->set(['x' => $position['x'], 'y' => $position['y']])
                ->set('revision', 'revision + 1', false)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->where('id', (int) $row['id'])
                ->where('revision', (int) $row['revision'])
                ->update();
        }
    }

    public function down()
    {
        // Corrected coordinates cannot be reconstructed safely.
    }

    private function samePosition(array $row, array $position): bool
    {
        return abs((float) $row['x'] - $position['x']) < 0.0005
            && abs((float) $row['y'] - $position['y']) < 0.0005;
    }
}
