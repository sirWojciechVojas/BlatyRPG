<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MaterializeShopAssortmentInstances extends Migration
{
    private const MARKER = 'materialized_shop_stock_v1';

    public function up()
    {
        if (!$this->db->tableExists('shop_container_template_items')) {
            return;
        }

        $rows = $this->db->table('shop_container_template_items stock')
            ->select('stock.*, containers.container_type, templates.name, templates.description')
            ->join('shop_containers containers', 'containers.id = stock.container_id')
            ->join('shop_templates templates', 'templates.id = stock.template_id')
            ->where('containers.container_type', 'SHOP')
            ->get()
            ->getResultArray();

        $this->db->transStart();
        foreach ($rows as $row) {
            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            for ($unit = 1; $unit <= $quantity; $unit++) {
                $this->db->table('shop_item_instances')->insert([
                    'campaign_id' => (int) $row['campaign_id'],
                    'template_id' => (int) $row['template_id'],
                    'name_override' => (string) ($row['name'] ?? ''),
                    'data_override_json' => json_encode([
                        '_materialization' => self::MARKER,
                        '_sourceStockId' => (int) $row['id'],
                        '_sourceUnit' => $unit,
                    ], JSON_UNESCAPED_UNICODE),
                    'note' => (string) ($row['description'] ?? ''),
                    'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $instanceId = (int) $this->db->insertID();

                $this->db->table('shop_container_instance_items')->insert([
                    'campaign_id' => (int) $row['campaign_id'],
                    'container_id' => (int) $row['container_id'],
                    'instance_id' => $instanceId,
                    'price_override' => $row['price_override'],
                    'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->table('shop_container_template_items')
                ->where('id', (int) $row['id'])
                ->delete();
        }
        $this->db->transComplete();
    }

    public function down()
    {
        if (!$this->db->tableExists('shop_item_instances')) {
            return;
        }

        $instances = $this->db->table('shop_item_instances instances')
            ->select('instances.*, placements.container_id, placements.price_override')
            ->join('shop_container_instance_items placements', 'placements.instance_id = instances.id')
            ->get()
            ->getResultArray();

        $groups = [];
        foreach ($instances as $instance) {
            $meta = json_decode((string) ($instance['data_override_json'] ?? ''), true);
            if (($meta['_materialization'] ?? null) !== self::MARKER) {
                continue;
            }
            $key = implode(':', [
                (int) $instance['campaign_id'],
                (int) $instance['container_id'],
                (int) $instance['template_id'],
                $instance['price_override'] === null ? 'null' : (int) $instance['price_override'],
            ]);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'campaign_id' => (int) $instance['campaign_id'],
                    'container_id' => (int) $instance['container_id'],
                    'template_id' => (int) $instance['template_id'],
                    'price_override' => $instance['price_override'],
                    'quantity' => 0,
                    'instance_ids' => [],
                ];
            }
            $groups[$key]['quantity']++;
            $groups[$key]['instance_ids'][] = (int) $instance['id'];
        }

        $this->db->transStart();
        foreach ($groups as $group) {
            $existing = $this->db->table('shop_container_template_items')
                ->where('container_id', $group['container_id'])
                ->where('template_id', $group['template_id'])
                ->get()
                ->getRowArray();
            if ($existing) {
                $this->db->table('shop_container_template_items')
                    ->where('id', (int) $existing['id'])
                    ->update(['quantity' => (int) ($existing['quantity'] ?? 0) + $group['quantity']]);
            } else {
                $this->db->table('shop_container_template_items')->insert([
                    'campaign_id' => $group['campaign_id'],
                    'container_id' => $group['container_id'],
                    'template_id' => $group['template_id'],
                    'quantity' => $group['quantity'],
                    'price_override' => $group['price_override'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->table('shop_container_instance_items')
                ->whereIn('instance_id', $group['instance_ids'])
                ->delete();
            $this->db->table('shop_item_instances')
                ->whereIn('id', $group['instance_ids'])
                ->delete();
        }
        $this->db->transComplete();
    }
}
