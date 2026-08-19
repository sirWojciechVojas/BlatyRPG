<?php

namespace App\Services\Character;

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\UserModel;
use App\Services\Authorization\AccessLevel;

/** Safe compatibility layer for the former cross-campaign character index. */
final class CharacterLegacyListService
{
    private $characters;
    private $campaigns;
    private $members;
    private $users;

    public function __construct(
        CharacterDirectoryService $characters,
        ?CampaignModel $campaigns = null,
        ?CampaignMemberModel $members = null,
        ?UserModel $users = null
    ) {
        $this->characters = $characters;
        $this->campaigns = $campaigns ?: new CampaignModel();
        $this->members = $members ?: new CampaignMemberModel();
        $this->users = $users ?: new UserModel();
    }

    public function list(array $auth, array $filters = []): array
    {
        $auth = $this->verifiedAuth($auth);
        $itemsById = [];
        $canCreate = false;
        foreach ($this->campaignIds($auth) as $campaignId) {
            try {
                $result = $this->characters->list($auth, $campaignId, $filters);
            } catch (CharacterException $exception) {
                if (in_array($exception->status(), [403, 404], true)) {
                    continue;
                }
                throw $exception;
            }
            $canCreate = $canCreate || !empty($result['capabilities']['canCreate']);
            foreach ($result['items'] as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id > 0 && $this->prefer($item, $itemsById[$id] ?? null)) {
                    $itemsById[$id] = $item;
                }
            }
        }
        $items = array_values($itemsById);
        usort($items, static function (array $left, array $right): int {
            $byName = strcasecmp((string) $left['name'], (string) $right['name']);
            return $byName ?: ((int) $left['id'] <=> (int) $right['id']);
        });
        return [
            'count' => count($items),
            'items' => $items,
            'capabilities' => ['canCreate' => $canCreate],
        ];
    }

    private function campaignIds(array $auth): array
    {
        if ($auth['role'] === 'admin') {
            $rows = $this->campaigns->select('id')->orderBy('id', 'ASC')->findAll();
            return array_map('intval', array_column($rows, 'id'));
        }
        $userId = (int) $auth['user_id'];
        $owned = $this->campaigns->select('id')
            ->where('game_master_id', $userId)->findAll();
        $memberships = $this->members->select('campaign_id')
            ->where('user_id', $userId)->where('is_active', 1)->findAll();
        $ids = array_merge(
            array_column($owned, 'id'),
            array_column($memberships, 'campaign_id')
        );
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $active = $this->campaigns->select('id')->whereIn('id', $ids)
            ->where('is_active', 1)->orderBy('id', 'ASC')->findAll();
        return array_map('intval', array_column($active, 'id'));
    }

    private function verifiedAuth(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new CharacterException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->users->where('id', $userId)->where('deleted_at', null)->first();
        if (!$user) {
            throw new CharacterException('unauthorized', 'Authentication is required.', 401);
        }
        $auth['user_id'] = $userId;
        $auth['role'] = strtolower((string) ($user['role'] ?? 'user'));
        return $auth;
    }

    private function prefer(array $candidate, ?array $current): bool
    {
        if ($current === null) {
            return true;
        }
        return AccessLevel::allows(
            $candidate['accessLevel'] ?? AccessLevel::NONE,
            $current['accessLevel'] ?? AccessLevel::NONE
        );
    }
}
