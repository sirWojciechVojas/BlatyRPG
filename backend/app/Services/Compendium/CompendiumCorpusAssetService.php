<?php

namespace App\Services\Compendium;

use App\Services\Campaign\CampaignException;
use App\Services\Handout\HandoutAssetStorage;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

/** Protected storage and delivery for files registered by a source package. */
final class CompendiumCorpusAssetService
{
    private $db;
    private $access;
    private $storage;
    private $media;

    public function __construct(?BaseConnection $db = null, ?CompendiumAccessService $access = null, ?HandoutAssetStorage $storage = null, ?MediaService $media = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->access = $access ?: new CompendiumAccessService($this->db);
        $this->storage = $storage ?: new HandoutAssetStorage(WRITEPATH . 'uploads/compendium-corpus');
        $this->media = $media ?: new MediaService($this->db);
    }

    public function upload(int $universeId, int $assetId, array $auth, ?UploadedFile $file): array
    {
        $context = $this->access->editorial($auth, $universeId, true);
        $worldId = (int) $context['world']['id'];
        $asset = $this->assetInWorld($assetId, $worldId);
        if (!$asset) throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        $incomingSize = $file ? (int) $file->getSize() : 0;
        $used = $this->worldUsage($worldId);
        $replacedSize = (!empty($asset['storage_key']) || !empty($asset['media_asset_id']))
            ? (int) ($asset['byte_size'] ?? 0) : 0;
        if ($incomingSize < 1 || $used - $replacedSize + $incomingSize > (int) $context['world']['storage_limit_bytes']) {
            throw new CampaignException('compendium_quota_exceeded', 'The world asset limit has been reached.', 422);
        }
        $stored = $this->storage->store($worldId, $file);
        $previousKey = $asset['storage_key'] ?? null;
        $central = null;
        try {
            if ($this->db->tableExists('media_assets')
                && $this->db->fieldExists('media_asset_id', 'compendium_corpus_assets')) {
                $central = $this->media->uploadFile($auth, $stored['path'], [
                    'filename' => $stored['originalName'], 'mimeType' => $stored['mimeType'],
                    'category' => strpos($stored['mimeType'], 'image/') === 0 ? 'other' : 'documents',
                    'visibility' => 'private', 'ownerUserId' => (int) $context['auth']['user_id'],
                ]);
            }
            $update = [
                'download_status' => 'available', 'checksum' => $stored['sha256'],
                'storage_key' => $stored['storageKey'], 'mime_type' => $stored['mimeType'],
                'byte_size' => $stored['byteSize'], 'error_message' => null, 'updated_at' => $this->now(),
            ];
            if ($central) {
                $update['storage_key'] = null;
                $update['media_asset_id'] = (int) $central['id'];
            }
            $this->db->table('compendium_corpus_assets')->where('id', $assetId)->update($update);
            if ($this->db->affectedRows() !== 1) throw new CampaignException('compendium_write_failed', 'Asset could not be saved.', 500);
            if ($central) $this->storage->remove($stored['storageKey']);
        } catch (MediaException $exception) {
            $this->storage->remove($stored['storageKey']);
            throw new CampaignException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
        } catch (\Throwable $error) {
            $this->storage->remove($stored['storageKey']);
            throw $error;
        }
        if ($previousKey && $previousKey !== $stored['storageKey']) $this->storage->remove((string) $previousKey);
        return ['asset' => [
            'id' => $assetId, 'filename' => $asset['filename'], 'available' => true,
            'mimeType' => $stored['mimeType'], 'byteSize' => (int) $stored['byteSize'],
            'fileUrl' => $this->fileUrl($assetId, null, $universeId),
        ]];
    }

    public function download(
        int $assetId,
        array $auth,
        ?int $campaignId,
        ?int $universeId,
        ?int $characterId = null
    ): array
    {
        $asset = $this->db->table('compendium_corpus_assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset || (empty($asset['storage_key']) && empty($asset['media_asset_id']))) {
            throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        }
        $allowed = false;
        if ($campaignId) {
            $context = $this->access->campaign($auth, $campaignId);
            $allowed = $this->campaignCanRead(
                $context,
                $assetId,
                $characterId
            );
        } elseif ($universeId) {
            $context = $this->access->editorial($auth, $universeId, false);
            $allowed = $this->assetInWorld($assetId, (int) $context['world']['id']) !== null;
        }
        if (!$allowed) throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        if (!empty($asset['media_asset_id'])) {
            try {
                $media = $this->media->getTrusted((int) $asset['media_asset_id']);
            } catch (MediaException $exception) {
                throw new CampaignException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
            return ['asset' => $asset, 'url' => $media['url']];
        }
        $path = $this->storage->path((string) $asset['storage_key']);
        if (!is_file($path)) throw new CampaignException('compendium_asset_not_found', 'Asset was not found.', 404);
        return ['asset' => $asset, 'path' => $path];
    }

    public function fileUrl(int $assetId, ?int $campaignId, ?int $universeId): string
    {
        $query = $campaignId ? 'campaignId=' . $campaignId : 'universeId=' . (int) $universeId;
        return '/api/compendium-corpus-assets/' . $assetId . '/file?' . $query;
    }

    private function campaignCanRead(
        array $context,
        int $assetId,
        ?int $characterId
    ): bool
    {
        $worldId = (int) $context['world']['id'];
        if (!empty($context['canSeeGm'])) return $this->assetInWorld($assetId, $worldId) !== null;
        $campaignId = (int) $context['campaign']['id'];
        $userId = (int) $context['auth']['user_id'];
        if ($characterId && !$this->canReadCharacter(
            $context,
            $characterId
        )) {
            return false;
        }
        $revealTarget = $characterId
            ? 'cr.character_id=?'
            : 'cr.character_id IS NULL AND (cr.user_id IS NULL OR cr.user_id=?)';
        $row = $this->db->query(
            'SELECT 1 FROM compendium_entity_assets ea JOIN compendium_entities ce ON ce.id=ea.entity_id '
            . 'WHERE ea.asset_id=? AND ce.world_id=? AND ce.deleted_at IS NULL '
            . 'AND ea.source_revision_id=ce.current_source_revision_id AND ('
            . "(ce.visibility IN ('public','player') AND ce.verification_status='verified') OR EXISTS ("
            . 'SELECT 1 FROM compendium_campaign_reveals cr WHERE cr.entity_id=ce.id AND cr.campaign_id=? '
            . 'AND cr.source_revision_id=ea.source_revision_id AND cr.section_keys_json IS NULL '
            . "AND cr.revoked_at IS NULL AND {$revealTarget})) LIMIT 1",
            [$assetId, $worldId, $campaignId, $characterId ?: $userId]
        )->getRowArray();
        return (bool) $row;
    }

    private function canReadCharacter(array $context, int $characterId): bool
    {
        if (!empty($context['campaignContext']['capabilities']['canManage'])) {
            return true;
        }
        $campaignId = (int) $context['campaign']['id'];
        $userId = (int) $context['auth']['user_id'];
        $campaignIdSql = (int) $campaignId;
        return (bool) $this->db->table('characters character_row')
            ->select('character_row.id')
            ->join(
                'character_campaigns campaign_assignment',
                'campaign_assignment.character_id=character_row.id '
                . "AND campaign_assignment.campaign_id={$campaignIdSql}",
                'left'
            )
            ->where('character_row.id', $characterId)
            ->groupStart()
            ->where('character_row.campaign_id', $campaignId)
            ->orWhere('campaign_assignment.campaign_id', $campaignId)
            ->groupEnd()
            ->groupStart()
            ->where('character_row.user_id', $userId)
            ->orWhere(
                'EXISTS (SELECT 1 FROM resource_permissions permission '
                . "WHERE permission.campaign_id={$campaignIdSql} "
                . "AND permission.resource_type='character' "
                . 'AND permission.resource_id=character_row.id '
                . 'AND permission.user_id=' . $userId
                . " AND permission.access_level='owner')",
                null,
                false
            )
            ->groupEnd()
            ->get()
            ->getRowArray();
    }

    private function assetInWorld(int $assetId, int $worldId): ?array
    {
        return $this->db->table('compendium_corpus_assets a')->select('a.*')
            ->join('compendium_entity_assets ea', 'ea.asset_id=a.id', 'inner')
            ->join('compendium_entities ce', 'ce.id=ea.entity_id', 'inner')
            ->where('a.id', $assetId)->where('ce.world_id', $worldId)->where('ce.deleted_at', null)
            ->limit(1)->get()->getRowArray() ?: null;
    }

    private function worldUsage(int $worldId): int
    {
        $authored = (int) ($this->db->table('compendium_assets')->selectSum('byte_size', 'used')
            ->where('world_id', $worldId)->where('deleted_at', null)->get()->getRowArray()['used'] ?? 0);
        $available = $this->db->fieldExists('media_asset_id', 'compendium_corpus_assets')
            ? '(a.storage_key IS NOT NULL OR a.media_asset_id IS NOT NULL)'
            : 'a.storage_key IS NOT NULL';
        $source = $this->db->query(
            'SELECT COALESCE(SUM(a.byte_size),0) AS used FROM compendium_corpus_assets a WHERE ' . $available . ' '
            . 'AND EXISTS (SELECT 1 FROM compendium_entity_assets ea JOIN compendium_entities ce ON ce.id=ea.entity_id '
            . 'WHERE ea.asset_id=a.id AND ce.world_id=? AND ce.deleted_at IS NULL)',
            [$worldId]
        )->getRowArray();
        return $authored + (int) ($source['used'] ?? 0);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
