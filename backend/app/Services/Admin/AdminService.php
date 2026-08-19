<?php

namespace App\Services\Admin;

use App\Models\CampaignModel;
use App\Models\UserModel;
use App\Services\Auth\AuthUserPresenter;
use CodeIgniter\Database\BaseConnection;

class AdminService
{
    private $db;
    private $users;
    private $campaigns;
    private $validator;
    private $presenter;

    public function __construct(
        ?BaseConnection $db = null,
        ?UserModel $users = null,
        ?CampaignModel $campaigns = null,
        ?AdminPayloadValidator $validator = null,
        ?AuthUserPresenter $presenter = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->users = $users ?: new UserModel($this->db);
        $this->campaigns = $campaigns ?: new CampaignModel($this->db);
        $this->validator = $validator ?: new AdminPayloadValidator();
        $this->presenter = $presenter ?: new AuthUserPresenter();
    }

    public function overview(array $auth): array
    {
        $admin = $this->verifiedAdmin($auth);
        $users = $this->users->where('deleted_at', null)
            ->orderBy('username', 'ASC')->findAll();
        $campaignRows = $this->campaigns
            ->select('campaigns.*, gm.username AS gm_username')
            ->join('users gm', 'gm.id = campaigns.game_master_id', 'left')
            ->orderBy('campaigns.name', 'ASC')->findAll();
        $membershipCounts = $this->groupedCounts('campaign_members', 'campaign_id');
        $userMembershipCounts = $this->groupedCounts('campaign_members', 'user_id');

        return [
            'currentUserId' => (int) $admin['id'],
            'users' => array_map(function (array $user) use ($userMembershipCounts): array {
                $presented = $this->presenter->present($user);
                $presented['campaignCount'] = $userMembershipCounts[(int) $user['id']] ?? 0;
                return $presented;
            }, $users),
            'campaigns' => array_map(static function (array $campaign) use ($membershipCounts): array {
                $campaignId = (int) $campaign['id'];
                return [
                    'id' => $campaignId,
                    'name' => (string) $campaign['name'],
                    'systemType' => (string) $campaign['system_type'],
                    'isActive' => (bool) $campaign['is_active'],
                    'gameMasterId' => (int) $campaign['game_master_id'],
                    'gameMasterName' => $campaign['gm_username'] ?? null,
                    'memberCount' => $membershipCounts[$campaignId] ?? 0,
                ];
            }, $campaignRows),
            'metrics' => [
                'users' => count($users),
                'admins' => count(array_filter($users, static function (array $user): bool {
                    return strtolower((string) $user['role']) === 'admin';
                })),
                'campaigns' => count($campaignRows),
            ],
        ];
    }

    public function createUser(array $auth, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $validated = $this->validator->validateCreateUser($payload);
        $this->assertValid($validated);
        $data = $validated['data'];
        $insert = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
            'role' => $data['role'],
        ];
        if (!$this->users->insert($insert)) {
            throw new AdminException(
                'validation_failed',
                'User could not be created.',
                422,
                $this->users->errors()
            );
        }
        return ['user' => $this->presenter->present(
            $this->users->find((int) $this->users->getInsertID())
        )];
    }

    public function changeUserRole(array $auth, int $userId, array $payload): array
    {
        if ($userId < 1) {
            throw new AdminException('not_found', 'User was not found.', 404);
        }
        $validated = $this->validator->validateRole($payload);
        $this->assertValid($validated);
        $role = $validated['data']['role'];

        $this->db->transBegin();
        try {
            $admins = $this->lockedAdministrators();
            $actorId = (int) ($auth['user_id'] ?? 0);
            if ($actorId < 1 || !empty($auth['anonymous'])) {
                throw new AdminException('unauthorized', 'Authentication is required.', 401);
            }
            $actor = $this->findById($admins, $actorId);
            if (!$actor) {
                $existingActor = $this->lockedUser($actorId);
                if (!$existingActor) {
                    throw new AdminException('unauthorized', 'Authentication is required.', 401);
                }
                throw new AdminException('forbidden', 'Administrator access is required.', 403);
            }

            $target = $this->findById($admins, $userId) ?: $this->lockedUser($userId);
            if (!$target) {
                throw new AdminException('not_found', 'User was not found.', 404);
            }
            if (strtolower((string) $target['role']) === 'admin' && $role !== 'admin') {
                if (count($admins) <= 1) {
                    throw new AdminException(
                        'last_admin',
                        'The last administrator cannot be demoted.',
                        409
                    );
                }
            }
            $updated = $this->db->table('users')->where('id', $userId)->update([
                'role' => $role,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (!$updated || !$this->db->transCommit()) {
                throw new AdminException('write_failed', 'User role could not be changed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ['user' => $this->presenter->present($this->users->find($userId))];
    }

    private function lockedAdministrators(): array
    {
        $builder = $this->db->table('users')
            ->where('role', 'admin')
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC');
        return $this->lockingQuery($builder->getCompiledSelect())->getResultArray();
    }

    private function lockedUser(int $userId): ?array
    {
        $builder = $this->db->table('users')
            ->where('id', $userId)
            ->where('deleted_at', null);
        return $this->lockingQuery($builder->getCompiledSelect())->getRowArray();
    }

    private function lockingQuery(string $sql)
    {
        $driver = strtolower((string) $this->db->DBDriver);
        if (in_array($driver, ['mysqli', 'postgre', 'sqlsrv', 'oci8'], true)) {
            $sql .= ' FOR UPDATE';
        }
        return $this->db->query($sql);
    }

    private function findById(array $users, int $userId): ?array
    {
        foreach ($users as $user) {
            if ((int) $user['id'] === $userId) {
                return $user;
            }
        }
        return null;
    }

    private function verifiedAdmin(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new AdminException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->users->where('id', $userId)->where('deleted_at', null)->first();
        if (!$user) {
            throw new AdminException('unauthorized', 'Authentication is required.', 401);
        }
        if (strtolower((string) $user['role']) !== 'admin') {
            throw new AdminException('forbidden', 'Administrator access is required.', 403);
        }
        return $user;
    }

    private function groupedCounts(string $table, string $column): array
    {
        $rows = $this->db->table($table)->select($column . ', COUNT(*) AS total')
            ->where('is_active', 1)->groupBy($column)->get()->getResultArray();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row[$column]] = (int) $row['total'];
        }
        return $counts;
    }

    private function assertValid(array $validated): void
    {
        if (!$validated['valid']) {
            throw new AdminException(
                'validation_failed',
                'Request payload is invalid.',
                422,
                $validated['errors']
            );
        }
    }
}
