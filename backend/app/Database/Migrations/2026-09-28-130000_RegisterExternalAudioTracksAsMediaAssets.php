<?php

namespace App\Database\Migrations;

use App\Services\Media\ExternalMediaUrl;
use CodeIgniter\Database\Migration;

/**
 * Registers pre-existing external audio entries in the central catalogue.
 *
 * This migration only records a canonical HTTP(S) source URL. It never
 * downloads audio, probes third-party hosts, or requires R2 credentials.
 */
final class RegisterExternalAudioTracksAsMediaAssets extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('media_assets')
            || !$this->db->tableExists('audio_tracks')
            || !$this->db->fieldExists('media_asset_id', 'audio_tracks')
            || !$this->db->fieldExists('source_url', 'media_assets')) {
            return;
        }

        $rows = $this->db->table('audio_tracks tracks')
            ->select('tracks.id, tracks.owner_user_id, tracks.title, tracks.tags_json, tracks.external_url')
            ->where('tracks.deleted_at', null)
            ->where('tracks.source_type', 'external')
            ->where('tracks.media_asset_id', null)
            ->where('tracks.external_url IS NOT NULL', null, false)
            ->where('tracks.external_url !=', '')
            ->orderBy('tracks.id', 'ASC')
            ->get()->getResultArray();

        foreach ($rows as $track) {
            $sourceUrl = ExternalMediaUrl::canonicalize((string) $track['external_url']);
            if ($sourceUrl === null) {
                continue;
            }
            $assetId = $this->assetId($sourceUrl, $track);
            if ($assetId === null) {
                continue;
            }
            $this->db->table('audio_tracks')
                ->where('id', (int) $track['id'])
                ->where('media_asset_id', null)
                ->update(['media_asset_id' => $assetId, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    public function down()
    {
        // The links are intentionally retained: an external asset can be shared
        // by several audio tracks and must not be removed with one migration.
    }

    private function assetId(string $sourceUrl, array $track): ?int
    {
        $fingerprint = ExternalMediaUrl::fingerprint($sourceUrl);
        $existing = $this->db->table('media_assets')->select('id')
            ->where('provider', 'external')
            ->where('provider_asset_id', $fingerprint)
            ->where('deleted_at', null)
            ->get()->getRowArray();
        if ($existing) {
            return (int) $existing['id'];
        }

        $tags = $track['tags_json'] ?? [];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }
        $tags = is_array($tags) ? array_values(array_unique(array_filter(array_map(
            static fn ($tag): string => mb_substr(trim((string) $tag), 0, 64),
            $tags
        )))) : [];
        $tags = array_values(array_unique(array_merge(['external', 'audio'], $tags)));
        $now = date('Y-m-d H:i:s');
        $this->db->table('media_assets')->insert([
            'owner_user_id' => !empty($track['owner_user_id']) ? (int) $track['owner_user_id'] : null,
            'campaign_id' => null,
            'provider' => 'external',
            'provider_container' => 'external',
            'provider_asset_id' => $fingerprint,
            'public_id' => null,
            'source_url' => $sourceUrl,
            'resource_type' => 'audio',
            'category' => 'audio',
            'name' => trim((string) ($track['title'] ?? '')) ?: ExternalMediaUrl::filename($sourceUrl),
            'description' => null,
            'tags' => json_encode($tags),
            'original_filename' => ExternalMediaUrl::filename($sourceUrl),
            'mime_type' => 'audio/*',
            'format' => strtolower(pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: null,
            'file_size' => null,
            'visibility' => 'public',
            'status' => 'ready',
            'availability_status' => 'unknown',
            'availability_checked_at' => null,
            'revision' => 1,
            'metadata' => json_encode([
                'source' => 'external_audio_track',
                'availability_policy' => 'not_checked_server_side',
            ]),
            'custom_metadata' => json_encode([]),
            'upload_expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        if ($id > 0) {
            return $id;
        }

        $existing = $this->db->table('media_assets')->select('id')
            ->where('provider', 'external')
            ->where('provider_asset_id', $fingerprint)
            ->where('deleted_at', null)
            ->get()->getRowArray();
        return $existing ? (int) $existing['id'] : null;
    }
}
