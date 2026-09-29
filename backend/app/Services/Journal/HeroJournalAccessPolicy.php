<?php

namespace App\Services\Journal;

final class HeroJournalAccessPolicy
{
    public function canView(array $entry, array $context, ?string $override = null): bool
    {
        $visibility = $override ?: (string) ($entry['visibility'] ?? 'private');
        $isOwner = (int) ($entry['owner_user_id'] ?? 0) === (int) ($context['userId'] ?? 0);
        if ($visibility === 'private') {
            return $isOwner;
        }
        if ($visibility === 'gm_player') {
            return $isOwner || !empty($context['isManager']);
        }
        if ($visibility === 'campaign') {
            return !empty($context['isManager'])
                || strtolower((string) ($context['membershipRole'] ?? '')) !== 'observer';
        }
        return $visibility === 'public_campaign';
    }

    public function canEdit(array $entry, array $context): bool
    {
        if ((int) ($entry['owner_user_id'] ?? 0) === (int) ($context['userId'] ?? 0)) {
            return true;
        }
        return !empty($context['isManager'])
            && (string) ($entry['visibility'] ?? 'private') !== 'private';
    }
}
