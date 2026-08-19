<?php

namespace App\Services\Character;

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\CharacterModel;
use App\Models\ShopOwnerClaimModel;
use App\Models\UserModel;
use App\Services\CharacterAssetService;
use CodeIgniter\Database\BaseConnection;

final class CharacterDirectoryService
{
    private $db;
    private $characters;
    private $campaigns;
    private $members;
    private $users;
    private $policy;
    private $permissionResolver;
    private $validator;
    private $assets;
    private $catalog;
    private $revisionWriter;

    public function __construct(
        ?BaseConnection $db = null,
        ?CharacterModel $characters = null,
        ?CampaignModel $campaigns = null,
        ?CampaignMemberModel $members = null,
        ?ShopOwnerClaimModel $claims = null,
        ?UserModel $users = null,
        ?CharacterAccessPolicy $policy = null,
        ?CharacterPayloadValidator $validator = null,
        ?CharacterAssetService $assets = null,
        ?CharacterPermissionResolver $permissionResolver = null,
        ?CharacterCatalogGuard $catalog = null,
        ?CharacterRevisionWriter $revisionWriter = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->characters = $characters ?: new CharacterModel($this->db);
        $this->campaigns = $campaigns ?: new CampaignModel($this->db);
        $this->members = $members ?: new CampaignMemberModel($this->db);
        $this->users = $users ?: new UserModel($this->db);
        $this->policy = $policy ?: new CharacterAccessPolicy();
        $this->permissionResolver = $permissionResolver
            ?: new CharacterPermissionResolver(null, $claims ?: new ShopOwnerClaimModel($this->db));
        $this->validator = $validator ?: new CharacterPayloadValidator();
        $this->assets = $assets ?: new CharacterAssetService();
        $this->catalog = $catalog ?: new CharacterCatalogGuard($this->db);
        $this->revisionWriter = $revisionWriter ?: new CharacterRevisionWriter($this->db);
    }

    public function list(array $auth, int $campaignId, array $filters = []): array
    {
        $context = $this->campaignContext($auth, $campaignId);
        $query = $this->characters;
        if (!empty($context['canManageAll'])) {
            $query->groupStart()->where('campaign_id', $campaignId)
                ->orWhere('campaign_id', null)->groupEnd();
        } else {
            $query->where('campaign_id', $campaignId);
        }
        foreach (['user_id', 'system_id'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, (int) $filters[$field]);
            }
        }
        $rows = $query->orderBy('name', 'ASC')->findAll();
        $levels = $this->permissionResolver->levelsFor(
            (int) $context['user_id'],
            $campaignId
        );
        $visible = [];
        foreach ($rows as $row) {
            $permissions = $this->policy->character(
                $context,
                $row,
                (int) $context['user_id'],
                $campaignId,
                $levels[(int) $row['id']] ?? null
            );
            if ($permissions['canView']) {
                $row['_permissions'] = $permissions;
                $visible[] = $row;
            }
        }
        $visible = $this->assets->hydrateCharacters($visible);
        $items = array_map([CharacterPresenter::class, 'present'], $visible);
        return [
            'count' => count($items),
            'items' => $items,
            'capabilities' => ['canCreate' => !empty($context['canManageAll'])],
        ];
    }

    public function show(array $auth, int $id, ?int $campaignId = null): array
    {
        list($row) = $this->authorizedCharacter($auth, $id, $campaignId, false);
        $row = $this->assets->hydrateCharacters([$row])[0];
        $character = CharacterPresenter::present($row);
        return $character + ['character' => $character];
    }

    public function update(array $auth, int $id, ?int $campaignId, array $payload): array
    {
        list($row) = $this->authorizedCharacter($auth, $id, $campaignId, true);
        $validated = $this->validator->validateUpdate($payload);
        if (!$validated['valid']) {
            throw new CharacterException(
                'validation_failed',
                'Character payload is invalid.',
                422,
                $validated['errors']
            );
        }
        $this->revisionWriter->update(
            $id,
            $row,
            $validated['data'],
            $validated['expectedRevision'],
            $validated['expectedUpdatedAt']
        );
        $result = $this->show($auth, $id, $campaignId);
        $result['message'] = 'Character was updated.';
        return $result;
    }

    public function create(array $auth, array $payload): array
    {
        $validated = $this->validator->validateCreate($payload);
        if (!$validated['valid']) {
            throw new CharacterException(
                'validation_failed',
                'Character payload is invalid.',
                422,
                $validated['errors']
            );
        }
        $data = $validated['data'];
        $context = $this->campaignContext($auth, (int) $data['campaign_id']);
        if (empty($context['canManageAll'])) {
            throw new CharacterException('forbidden', 'Only a campaign manager can create characters.', 403);
        }
        $this->catalog->assertActiveGame((int) $data['system_id'], (int) $data['universe_id']);
        $data['user_id'] = null;
        $data['revision'] = 1;
        $this->db->transBegin();
        try {
            if (!$this->characters->insert($data)) {
                throw new CharacterException(
                    'validation_failed',
                    'Character could not be created.',
                    422,
                    $this->characters->errors()
                );
            }
            $id = (int) $this->characters->getInsertID();
            $this->assignCreatedAssetSet($id, $validated['assetSetId']);
            if ($this->db->transStatus() === false) {
                throw new CharacterException(
                    'character_write_failed',
                    'Character could not be created.',
                    500
                );
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $result = $this->show($auth, $id, (int) $data['campaign_id']);
        $result['message'] = 'Character was created.';
        return $result;
    }

    public function delete(array $auth, int $id, ?int $campaignId = null): void
    {
        list(, $permissions) = $this->authorizedCharacter($auth, $id, $campaignId, true);
        if (!$permissions['canDelete']) {
            throw new CharacterException('forbidden', 'You cannot delete this character.', 403);
        }
        $this->db->transBegin();
        try {
            $this->assets->releaseCharacterSet($id, false);
            if (!$this->characters->delete($id) || $this->db->transStatus() === false) {
                throw new CharacterException('character_write_failed', 'Character could not be deleted.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function assertEditable(array $auth, int $id, ?int $campaignId = null): array
    {
        list($row) = $this->authorizedCharacter($auth, $id, $campaignId, true);
        return $row;
    }

    public function assertAssetSetManager(array $auth): array
    {
        $auth = $this->verifiedAuth($auth);
        if (!in_array($auth['role'], ['gm', 'admin'], true)) {
            throw new CharacterException('forbidden', 'GM permissions are required.', 403);
        }
        return $auth;
    }

    private function assignCreatedAssetSet(int $characterId, ?int $assetSetId): void
    {
        if ($assetSetId === null) {
            return;
        }
        $result = $this->assets->assignSetToCharacter($characterId, $assetSetId, false);
        if (empty($result['ok'])) {
            throw new CharacterException(
                (string) ($result['code'] ?? 'asset_set_assignment_failed'),
                'Asset set could not be assigned.',
                (int) ($result['status'] ?? 500)
            );
        }
    }

    private function authorizedCharacter(
        array $auth,
        int $id,
        ?int $requestedCampaignId,
        bool $forMutation
    ): array {
        $row = $id > 0 ? $this->characters->find($id) : null;
        if (!$row) {
            throw new CharacterException('character_not_found', 'Character was not found.', 404);
        }
        $campaignId = $requestedCampaignId ?: (int) ($row['campaign_id'] ?? 0);
        if ($campaignId < 1) {
            throw new CharacterException('campaign_required', 'Campaign id is required.', 422);
        }
        $context = $this->campaignContext($auth, $campaignId);
        $levels = $this->permissionResolver->levelsFor((int) $context['user_id'], $campaignId);
        $permissions = $this->policy->character(
            $context,
            $row,
            (int) $context['user_id'],
            $campaignId,
            $levels[(int) $row['id']] ?? null
        );
        if (!$permissions['canView'] || ($forMutation && !$permissions['canEdit'])) {
            throw new CharacterException('forbidden', 'Character is outside your access scope.', 403);
        }
        $row['_permissions'] = $permissions;
        return [$row, $permissions];
    }

    private function campaignContext(array $auth, int $campaignId): array
    {
        if ($campaignId < 1) {
            throw new CharacterException('campaign_required', 'Campaign id is required.', 422);
        }
        $auth = $this->verifiedAuth($auth);
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            throw new CharacterException('campaign_not_found', 'Campaign was not found.', 404);
        }
        $membership = $this->members->where('campaign_id', $campaignId)
            ->where('user_id', (int) $auth['user_id'])->first();
        $access = $this->policy->campaign($auth, $campaign, $membership);
        if (!$access['canAccess']) {
            throw new CharacterException('forbidden', 'Campaign is outside your access scope.', 403);
        }
        return $access + $auth + ['isAdmin' => $auth['role'] === 'admin'];
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
}
