<?php

namespace App\Services\Handout;

use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

/** Applies campaign audience rules before a handout payload or asset is exposed. */
final class HandoutAccessService
{
    private $db;
    private $guard;

    public function __construct(?BaseConnection $db = null, ?CampaignGuardService $guard = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->guard = $guard ?: new CampaignGuardService();
    }

    public function libraryOwner(array $auth): int
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        }
        return $userId;
    }

    public function campaign(array $auth, int $campaignId): array
    {
        return $this->guard->context($auth, $campaignId);
    }

    public function canView(array $context, array $journal): bool
    {
        if (!empty($context['capabilities']['canManage'])) return true;
        $mode = (string) ($journal['audience_mode'] ?? 'private');
        if ($mode === 'all_active_members') return true;
        if ($mode !== 'selected_active_members') return false;
        return (bool) $this->db->table('journal_recipients')
            ->where('journal_id', (int) $journal['id'])
            ->where('user_id', (int) $context['auth']['user_id'])
            ->countAllResults();
    }

    public function canEdit(array $context, array $journal): bool
    {
        return !empty($context['capabilities']['canManage'])
            && (int) ($context['auth']['user_id'] ?? 0) === (int) ($journal['author_user_id'] ?? 0);
    }

    public function canPresent(array $context): bool
    {
        return !empty($context['capabilities']['canManage']);
    }

    public function requireView(array $context, array $journal): void
    {
        if (!$this->canView($context, $journal)) {
            throw new CampaignException('handout_not_found', 'Handout was not found.', 404);
        }
    }

    public function requireEdit(array $context, array $journal): void
    {
        if (!$this->canEdit($context, $journal)) {
            throw new CampaignException('forbidden', 'Only the publishing game master may edit this handout.', 403);
        }
    }

    public function requirePresent(array $context): void
    {
        if (!$this->canPresent($context)) {
            throw new CampaignException('forbidden', 'Campaign manager access is required.', 403);
        }
    }
}
