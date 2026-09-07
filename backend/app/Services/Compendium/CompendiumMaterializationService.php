<?php

namespace App\Services\Compendium;

use App\Services\Campaign\CampaignException;
use App\Services\Character\CharacterDirectoryService;
use App\Services\CharacterAssetService;
use App\Services\Token\SceneTokenService;
use CodeIgniter\Database\BaseConnection;

final class CompendiumMaterializationService
{
    private $db;
    private $access;

    public function __construct(?BaseConnection $db = null, ?CompendiumAccessService $access = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->access = $access ?: new CompendiumAccessService($this->db);
    }

    public function materialize(int $campaignId, int $entryId, array $auth, array $payload): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        if (empty($context['canMaterialize'])) throw new CampaignException('forbidden', 'Campaign character management is required.', 403);
        $row = $this->db->table('compendium_entries e')
            ->select('e.id AS entry_id, e.world_id, v.*, t.code AS type_code')
            ->join('compendium_entry_versions v', 'v.id=e.published_version_id', 'inner')
            ->join('compendium_entry_types t', 't.id=v.type_id', 'inner')
            ->where('e.id', $entryId)->where('e.status', 'active')->where('e.deleted_at', null)->get()->getRowArray();
        if (!$row || (int) $row['world_id'] !== (int) $context['world']['id'] || $row['type_code'] !== 'creature') {
            throw new CampaignException('compendium_entry_not_found', 'Creature entry was not found.', 404);
        }
        $systemId = (int) ($context['campaign']['rpg_system_id'] ?? 0);
        $blocks = $this->json($row['stat_blocks_json']);
        $block = null;
        foreach ($blocks as $candidate) if ((int) ($candidate['systemId'] ?? 0) === $systemId) $block = $candidate;
        if (!$block) throw new CampaignException('compendium_stat_block_missing', 'The creature has no stat block for this campaign system.', 409);
        $name = trim((string) ($payload['name'] ?? $row['title']));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) throw new CampaignException('validation_failed', 'NPC name is invalid.', 422);
        $assetSetId = null; $tokenImage = '';
        $assets = is_array($block['assetPublicIds'] ?? null) ? $block['assetPublicIds'] : [];
        $requiredAssets = ['avatar', 'portrait', 'token', 'fullbody'];
        if (!empty($payload['createToken']) && filter_var($payload['sceneId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new CampaignException('validation_failed', 'A scene is required when creating a token.', 422, ['sceneId' => 'Select a scene.']);
        }
        $this->db->transBegin();
        try {
            $availableAssets = array_filter($assets, static fn ($value): bool => is_string($value) && trim($value) !== '');
            if (!array_diff($requiredAssets, array_keys($availableAssets))) {
                $set = (new CharacterAssetService())->createAvailableSet($name, array_intersect_key($availableAssets, array_flip($requiredAssets)));
                $assetSetId = (int) ($set['id'] ?? 0) ?: null;
                $tokenImage = (string) ($set['assets']['token']['url'] ?? '');
            }
            $characters = new CharacterDirectoryService($this->db);
            $created = $characters->create($auth, [
                'campaignId' => $campaignId, 'systemId' => $systemId,
                'universeId' => (int) $context['world']['universe_id'], 'name' => $name,
                'data' => is_array($block['data'] ?? null) ? $block['data'] : [],
                'avatarUrl' => $tokenImage, 'assetSetId' => $assetSetId,
            ]);
            $character = $created['character'] ?? $created;
            $token = null;
            $sceneId = filter_var($payload['sceneId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!empty($payload['createToken']) && $sceneId !== false) {
                $defaults = is_array($block['token'] ?? null) ? $block['token'] : [];
                $tokenPayload = array_intersect_key($defaults, array_flip(['width', 'height', 'disposition', 'hidden', 'locked', 'vision']));
                $tokenPayload += ['characterId' => (int) $character['id'], 'name' => $name,
                    'imageUrl' => $tokenImage, 'x' => (float) ($payload['x'] ?? 0), 'y' => (float) ($payload['y'] ?? 0)];
                $tokenResult = (new SceneTokenService($this->db))->create($campaignId, (int) $sceneId, $auth, $tokenPayload);
                $token = $tokenResult['token'] ?? null;
            }
            $this->db->table('compendium_materializations')->insert([
                'version_id' => (int) $row['id'], 'campaign_id' => $campaignId,
                'character_id' => (int) $character['id'], 'token_id' => $token ? (int) $token['id'] : null,
                'created_by_user_id' => (int) $context['auth']['user_id'], 'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($this->db->transStatus() === false) throw new CampaignException('compendium_materialization_failed', 'NPC could not be created.', 500);
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            if ($error instanceof CampaignException) throw $error;
            if (method_exists($error, 'status') && method_exists($error, 'errorCode')) {
                throw new CampaignException($error->errorCode(), $error->getMessage(), $error->status());
            }
            throw new CampaignException('compendium_materialization_failed', 'NPC could not be created.', 500);
        }
        return ['character' => $character, 'token' => $token, 'sourceVersionId' => (int) $row['id']];
    }

    private function json($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
