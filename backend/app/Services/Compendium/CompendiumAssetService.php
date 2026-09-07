<?php

namespace App\Services\Compendium;

use App\Services\Campaign\CampaignException;
use App\Services\Handout\HandoutAssetStorage;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

final class CompendiumAssetService
{
    private $db;
    private $access;
    private $storage;

    public function __construct(?BaseConnection $db = null, ?CompendiumAccessService $access = null, ?HandoutAssetStorage $storage = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->access = $access ?: new CompendiumAccessService($this->db);
        $this->storage = $storage ?: new HandoutAssetStorage(WRITEPATH . 'uploads/compendium');
    }

    public function upload(int $universeId, array $auth, ?UploadedFile $file): array
    {
        $context = $this->access->editorial($auth, $universeId, true);
        $world = $context['world'];
        $used = (int) ($this->db->table('compendium_assets')->selectSum('byte_size', 'used')
            ->where('world_id', (int) $world['id'])->where('deleted_at', null)->get()->getRowArray()['used'] ?? 0);
        $size = $file ? (int) $file->getSize() : 0;
        if ($size < 1 || $used + $size > (int) $world['storage_limit_bytes']) {
            throw new CampaignException('compendium_quota_exceeded', 'The world asset limit has been reached.', 422);
        }
        $stored = $this->storage->store((int) $world['id'], $file);
        $now = date('Y-m-d H:i:s');
        $this->db->table('compendium_assets')->insert([
            'world_id' => (int) $world['id'], 'uploaded_by_user_id' => (int) $context['auth']['user_id'],
            'storage_key' => $stored['storageKey'], 'original_name' => $stored['originalName'],
            'mime_type' => $stored['mimeType'], 'byte_size' => $stored['byteSize'],
            'sha256' => $stored['sha256'], 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        return ['asset' => $this->present((int) $this->db->insertID(), $stored)];
    }

    public function index(int $universeId, array $auth): array
    {
        $context = $this->access->editorial($auth, $universeId, false);
        $rows = $this->db->table('compendium_assets a')->select('a.*')
            ->where('a.world_id', (int) $context['world']['id'])->where('a.deleted_at', null)
            ->orderBy('a.original_name')->get()->getResultArray();
        return ['items' => array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'], 'name' => (string) $row['original_name'],
                'mimeType' => (string) $row['mime_type'], 'byteSize' => (int) $row['byte_size'],
                'referenceCount' => $this->db->table('compendium_asset_references')
                    ->where('asset_id', (int) $row['id'])->countAllResults(),
                'createdAt' => $row['created_at'],
            ];
        }, $rows)];
    }

    public function delete(int $universeId, int $assetId, array $auth): array
    {
        $context = $this->access->editorial($auth, $universeId, true);
        $asset = $this->db->table('compendium_assets')->where('id', $assetId)
            ->where('world_id', (int) $context['world']['id'])->where('deleted_at', null)->get()->getRowArray();
        if (!$asset) throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        if ($this->db->table('compendium_asset_references')->where('asset_id', $assetId)->countAllResults()) {
            throw new CampaignException('compendium_asset_in_use', 'An asset referenced by an entry version cannot be deleted.', 409);
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('compendium_assets')->where('id', $assetId)->update(['deleted_at' => $now, 'updated_at' => $now]);
        $this->storage->remove((string) $asset['storage_key']);
        return ['deleted' => true, 'id' => $assetId];
    }

    public function download(int $assetId, array $auth, ?int $campaignId = null): array
    {
        $asset = $this->db->table('compendium_assets')->where('id', $assetId)->where('deleted_at', null)->get()->getRowArray();
        if (!$asset) throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        $world = $this->db->table('compendium_worlds')->where('id', (int) $asset['world_id'])->get()->getRowArray();
        $allowed = false;
        $canSeeGm = false;
        try {
            $editorial = $this->access->editorial($auth, (int) $world['universe_id'], false);
            $allowed = !empty($editorial['canRead']);
            $canSeeGm = !empty($editorial['canSeeGm']);
        } catch (CampaignException $ignored) {
            // A campaign member may still be allowed below.
        }
        if (!$allowed && $campaignId) {
            $campaign = $this->access->campaign($auth, $campaignId);
            if ((int) $campaign['world']['id'] === (int) $asset['world_id']) {
                $canSeeGm = !empty($campaign['canSeeGm']);
                $refs = $this->db->table('compendium_asset_references ar')
                    ->join('compendium_entries e', 'e.published_version_id = ar.version_id', 'inner')
                    ->join('compendium_entry_versions v', 'v.id = ar.version_id', 'inner')
                    ->where('ar.asset_id', $assetId)->where('e.status', 'active')->where('e.deleted_at', null);
                if (!$canSeeGm) $refs->where('ar.audience', 'public')->where('v.visibility', 'players');
                $allowed = (bool) $refs->countAllResults();
            }
        }
        if (!$allowed) throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        $path = $this->storage->path((string) $asset['storage_key']);
        if (!is_file($path)) throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        return ['asset' => $asset, 'path' => $path, 'canSeeGm' => $canSeeGm];
    }

    private function present(int $id, array $stored): array
    {
        return ['id' => $id, 'name' => $stored['originalName'], 'mimeType' => $stored['mimeType'],
            'byteSize' => (int) $stored['byteSize']];
    }
}
