<?php

namespace App\Services\Admin;

use CodeIgniter\Database\BaseConnection;

final class AdminOverviewAnalytics
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function build(array $users, array $campaigns, array $memberships): array
    {
        $activeSessions = $this->activeSessions();
        return [
            'metrics' => $this->metrics($users, $campaigns, $memberships, $activeSessions),
            'analytics' => [
                'accountRoles' => $this->distribution($users, 'role', ['user', 'admin']),
                'campaignStatuses' => $this->campaignStatuses($campaigns),
                'membershipRoles' => $this->distribution(
                    $memberships,
                    'role',
                    ['gm', 'assistant', 'player', 'observer']
                ),
                'systems' => $this->distribution($campaigns, 'system_type'),
                'growth' => $this->growth($users, $campaigns),
            ],
            'activity' => $this->activity($users, $campaigns),
            'system' => [
                'generatedAt' => date(DATE_ATOM),
                'environment' => ENVIRONMENT,
                'phpVersion' => PHP_VERSION,
                'database' => (string) $this->db->DBDriver,
                'activeSessions' => $activeSessions,
            ],
        ];
    }

    private function metrics(
        array $users,
        array $campaigns,
        array $memberships,
        int $activeSessions
    ): array
    {
        return [
            'users' => count($users),
            'admins' => count(array_filter($users, static fn (array $row): bool =>
                strtolower((string) ($row['role'] ?? '')) === 'admin')),
            'campaigns' => count($campaigns),
            'activeCampaigns' => count(array_filter($campaigns, static fn (array $row): bool =>
                !empty($row['is_active']))),
            'memberships' => count($memberships),
            'activeSessions' => $activeSessions,
        ];
    }

    private function distribution(array $rows, string $field, array $order = []): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) ($row[$field] ?? '')));
            if ($key !== '') {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        $keys = array_values(array_unique(array_merge($order, array_keys($counts))));
        return array_values(array_map(static fn (string $key): array => [
            'key' => $key,
            'value' => $counts[$key] ?? 0,
        ], $keys));
    }

    private function campaignStatuses(array $campaigns): array
    {
        $rows = array_map(static function (array $campaign): array {
            $status = strtolower(trim((string) ($campaign['status'] ?? '')));
            if ($status === '') {
                $status = !empty($campaign['is_active']) ? 'active' : 'paused';
            }
            return ['status' => $status];
        }, $campaigns);
        return $this->distribution($rows, 'status', ['active', 'paused', 'archived']);
    }

    private function growth(array $users, array $campaigns): array
    {
        $weeks = [];
        $monday = new \DateTimeImmutable('monday this week 00:00:00');
        for ($offset = 7; $offset >= 0; $offset--) {
            $start = $monday->modify("-{$offset} weeks");
            $key = $start->format('Y-m-d');
            $weeks[$key] = [
                'label' => $start->format('d.m'),
                'users' => 0,
                'campaigns' => 0,
            ];
        }
        $this->countByWeek($weeks, $users, 'users');
        $this->countByWeek($weeks, $campaigns, 'campaigns');
        return array_values($weeks);
    }

    private function countByWeek(array &$weeks, array $rows, string $series): void
    {
        foreach ($rows as $row) {
            $created = strtotime((string) ($row['created_at'] ?? ''));
            if (!$created) {
                continue;
            }
            $key = date('Y-m-d', strtotime('monday this week', $created));
            if (isset($weeks[$key])) {
                $weeks[$key][$series]++;
            }
        }
    }

    private function activity(array $users, array $campaigns): array
    {
        $items = [];
        foreach ($users as $user) {
            $items[] = [
                'type' => 'user_created',
                'label' => (string) ($user['username'] ?? ''),
                'occurredAt' => $user['created_at'] ?? null,
            ];
        }
        foreach ($campaigns as $campaign) {
            $items[] = [
                'type' => 'campaign_activity',
                'label' => (string) ($campaign['name'] ?? ''),
                'occurredAt' => $campaign['last_activity_at']
                    ?? $campaign['updated_at'] ?? $campaign['created_at'] ?? null,
            ];
        }
        usort($items, static fn (array $left, array $right): int =>
            strcmp((string) $right['occurredAt'], (string) $left['occurredAt']));
        return array_slice(array_values(array_filter($items, static fn (array $item): bool =>
            !empty($item['occurredAt']))), 0, 20);
    }

    private function activeSessions(): int
    {
        if (!$this->db->tableExists('auth_sessions')) {
            return 0;
        }
        return $this->db->table('auth_sessions')
            ->where('revoked_at', null)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->countAllResults();
    }
}
