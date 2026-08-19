<?php

namespace App\Services\Campaign;

final class CampaignInvitationValidator
{
    public function validate(array $payload): array
    {
        $allowed = ['userId', 'user_id', 'identifier', 'role', 'message'];
        $unknown = array_diff(array_keys($payload), $allowed);
        $errors = $unknown
            ? ['payload' => 'Unsupported fields: ' . implode(', ', $unknown) . '.']
            : [];
        $data = [];
        $hasCamel = array_key_exists('userId', $payload);
        $hasSnake = array_key_exists('user_id', $payload);
        if ($hasCamel && $hasSnake && $payload['userId'] !== $payload['user_id']) {
            $errors['userId'] = 'Conflicting aliases are not allowed.';
        }
        $rawId = $hasCamel ? $payload['userId'] : ($payload['user_id'] ?? null);
        if ($rawId !== null && $rawId !== '') {
            $userId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($userId === false) {
                $errors['userId'] = 'User id must be a positive integer.';
            } else {
                $data['userId'] = (int) $userId;
            }
        }
        if (array_key_exists('identifier', $payload)) {
            $identifier = trim((string) $payload['identifier']);
            if ($identifier === '' || mb_strlen($identifier) > 255) {
                $errors['identifier'] = 'Identifier is required and cannot exceed 255 characters.';
            } else {
                $data['identifier'] = $identifier;
            }
        }
        if (!isset($data['userId']) && !isset($data['identifier'])) {
            $errors['identifier'] = 'Provide a user id, username or email.';
        }
        $role = CampaignRole::normalize($payload['role'] ?? CampaignRole::PLAYER);
        if ($role === null) {
            $errors['role'] = 'Use gm, assistant, player or observer.';
        } else {
            $data['role'] = $role;
        }
        if (array_key_exists('message', $payload)) {
            $message = trim((string) $payload['message']);
            if (mb_strlen($message) > 500) {
                $errors['message'] = 'Message cannot exceed 500 characters.';
            } else {
                $data['message'] = $message === '' ? null : $message;
            }
        }
        return ['valid' => !$errors, 'data' => $data, 'errors' => $errors];
    }
}
