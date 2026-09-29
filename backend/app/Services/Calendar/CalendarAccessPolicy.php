<?php

namespace App\Services\Calendar;

final class CalendarAccessPolicy
{
    public function canManage(array $context): bool
    {
        return !empty($context['isAdmin'])
            || !empty($context['isGameMaster'])
            || !empty($context['isOwner']);
    }

    public function canViewEvent(array $event, array $context, array $participantUserIds = []): bool
    {
        if ($this->canManage($context)) {
            return true;
        }
        $visibility = (string) ($event['visibility'] ?? 'all');
        if ($visibility === 'all') {
            return true;
        }
        if ($visibility === 'gm') {
            return false;
        }
        return $visibility === 'participants'
            && in_array((int) $context['auth']['user_id'], array_map('intval', $participantUserIds), true);
    }

    public function requireManage(array $context): void
    {
        if (!$this->canManage($context)) {
            throw new CalendarException(
                'forbidden',
                'Only the campaign game master or administrator may change the calendar.',
                403
            );
        }
    }
}
