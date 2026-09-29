<?php

namespace App\Services\Compendium;

use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

final class CompendiumAccessService
{
    private $db;
    private $campaigns;

    public function __construct(?BaseConnection $db = null, ?CampaignGuardService $campaigns = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->campaigns = $campaigns ?: new CampaignGuardService();
    }

    public function editorial(array $auth, int $universeId, bool $requireEditor = false): array
    {
        $this->authenticated($auth);
        $world = $this->world($universeId);
        $userId = (int) $auth['user_id'];
        $admin = strtolower((string) ($auth['role'] ?? '')) === 'admin';
        $owner = (int) ($world['owner_user_id'] ?? 0) === $userId;
        $editor = $owner || (bool) $this->db->table('compendium_editors')
            ->where('world_id', (int) $world['id'])->where('user_id', $userId)->countAllResults();
        $context = [
            'auth' => $auth, 'world' => $world, 'campaign' => null,
            'canRead' => $admin || $editor, 'canSeeGm' => $admin || $editor,
            'canEdit' => $admin || $editor, 'canManageSchema' => $admin || $owner,
            'canManageEditors' => $admin || $owner, 'canAssignOwner' => $admin,
            'canMaterialize' => false,
        ];
        if (!$context['canRead'] || ($requireEditor && !$context['canEdit'])) {
            throw new CampaignException('compendium_not_found', 'Compendium was not found.', 404);
        }
        return $context;
    }

    public function campaign(array $auth, int $campaignId): array
    {
        $context = $this->campaigns->context($auth, $campaignId);
        $universeId = (int) ($context['campaign']['rpg_universe_id'] ?? 0);
        if ($universeId < 1) {
            throw new CampaignException('compendium_unavailable', 'The campaign has no world assigned.', 409);
        }
        $world = $this->world($universeId);
        $canSeeGm = !empty($context['isAdmin'])
            || !empty($context['isGameMaster'])
            || ($context['campaignRole'] ?? null) === 'assistant';
        return [
            'auth' => $context['auth'], 'world' => $world,
            'campaign' => $context['campaign'], 'campaignContext' => $context,
            'canRead' => true, 'canSeeGm' => $canSeeGm,
            'canEdit' => false, 'canManageSchema' => false,
            'canManageEditors' => false, 'canAssignOwner' => false,
            'canMaterialize' => !empty($context['capabilities']['canManageCharacters'])
                || !empty($context['capabilities']['canManage']),
        ];
    }

    public function world(int $universeId): array
    {
        if ($universeId < 1 || !$this->db->table('rpg_universes')->where('id', $universeId)->countAllResults()) {
            throw new CampaignException('compendium_not_found', 'Compendium was not found.', 404);
        }
        $world = $this->db->table('compendium_worlds')->where('universe_id', $universeId)->get()->getRowArray();
        if ($world) return $world;
        $now = date('Y-m-d H:i:s');
        $configuredQuota = getenv('COMPENDIUM_WORLD_QUOTA_BYTES');
        $quota = ctype_digit((string) $configuredQuota) ? max(1, (int) $configuredQuota) : 524288000;
        $this->db->table('compendium_worlds')->ignore(true)->insert([
            'universe_id' => $universeId, 'owner_user_id' => null,
            'storage_limit_bytes' => $quota, 'revision' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $world = $this->db->table('compendium_worlds')->where('universe_id', $universeId)->get()->getRowArray();
        if (!$world) throw new CampaignException('compendium_write_failed', 'Compendium could not be initialized.', 500);
        return $world;
    }

    private function authenticated(array $auth): void
    {
        if ((int) ($auth['user_id'] ?? 0) < 1 || !empty($auth['anonymous'])) {
            throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        }
    }
}
