<?php

namespace App\Services\Auth;

use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;

final class AuthAccountService
{
    private $db;
    private $users;
    private $sessions;
    private $presenter;

    public function __construct(
        ?BaseConnection $db = null,
        ?UserModel $users = null,
        ?AuthSessionService $sessions = null,
        ?AuthUserPresenter $presenter = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->users = $users ?: new UserModel($this->db);
        $this->sessions = $sessions ?: new AuthSessionService(null, new UserModel($this->db));
        $this->presenter = $presenter ?: new AuthUserPresenter();
    }

    public function register(array $data): array
    {
        if ($this->identityExists($data['username'], $data['email'])) {
            throw new AuthException(
                'registration_failed',
                'Registration could not be completed with the supplied data.',
                422
            );
        }
        $inserted = $this->users->insert([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
            'role' => UserRole::PLAYER,
        ]);
        if (!$inserted) {
            throw new AuthException(
                'registration_failed',
                'Registration could not be completed with the supplied data.',
                422
            );
        }
        return $this->activeUser((int) $this->users->getInsertID());
    }

    public function authenticate(string $identifier, string $password): array
    {
        $user = $this->users->withDeleted()
            ->groupStart()
            ->where('email', strtolower($identifier))
            ->orWhere('username', $identifier)
            ->groupEnd()
            ->first();
        $storedHash = is_array($user) ? (string) ($user['password_hash'] ?? '') : '';
        $hashForCheck = $storedHash !== ''
            ? $storedHash : password_hash('InvalidCredentialCheck123', PASSWORD_DEFAULT);
        $valid = password_verify($password, $hashForCheck);
        if (!$user || !$valid || !empty($user['deleted_at'])
            || !UserRole::isSupported($user['role'] ?? '')) {
            throw new AuthException('invalid_credentials', 'Invalid login or password.', 401);
        }
        if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
            $this->users->update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $user = $this->activeUser((int) $user['id']);
        }
        $user['role'] = UserRole::normalize($user['role']);
        return $user;
    }

    public function activeUser(int $userId): array
    {
        $user = $userId > 0 ? $this->users->find($userId) : null;
        if (!$user || !UserRole::isSupported($user['role'] ?? '')) {
            throw new AuthException('unauthorized', 'Authentication is required.', 401);
        }
        $user['role'] = UserRole::normalize($user['role']);
        return $user;
    }

    public function updateProfile(int $userId, array $data): array
    {
        $this->activeUser($userId);
        if (isset($data['username']) && $this->valueUsedByAnother('username', $data['username'], $userId)) {
            throw new AuthException('profile_invalid', 'Profile could not be updated.', 422);
        }
        if (isset($data['email']) && $this->valueUsedByAnother('email', $data['email'], $userId)) {
            throw new AuthException('profile_invalid', 'Profile could not be updated.', 422);
        }
        $updated = $this->db->table('users')->where('id', $userId)
            ->where('deleted_at', null)->update($data + ['updated_at' => date('Y-m-d H:i:s')]);
        if (!$updated) {
            throw new AuthException('profile_write_failed', 'Profile could not be updated.', 500);
        }
        return $this->activeUser($userId);
    }

    public function changePassword(
        int $userId,
        string $currentPassword,
        string $newPassword,
        ?string $ip,
        ?string $userAgent
    ): array {
        $user = $this->activeUser($userId);
        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            throw new AuthException('invalid_current_password', 'Current password is invalid.', 401);
        }
        return $this->replacePasswordAndSessions($user, $newPassword, $ip, $userAgent);
    }

    public function replacePasswordAndSessions(
        array $user,
        string $newPassword,
        ?string $ip,
        ?string $userAgent
    ): array {
        $userId = (int) $user['id'];
        $this->db->transBegin();
        try {
            if (!$this->users->update($userId, [
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ])) {
                throw new AuthException('password_write_failed', 'Password could not be changed.', 500);
            }
            $this->sessions->revokeAll($userId);
            $user = $this->activeUser($userId);
            $session = $this->sessions->issue($user, $ip, $userAgent);
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new AuthException('password_write_failed', 'Password could not be changed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['user' => $user, 'session' => $session];
    }

    public function present(array $user): array
    {
        return $this->presenter->present($user);
    }

    private function identityExists(string $username, string $email): bool
    {
        return $this->db->table('users')->groupStart()
            ->where('username', $username)->orWhere('email', $email)
            ->groupEnd()->countAllResults() > 0;
    }

    private function valueUsedByAnother(string $field, string $value, int $userId): bool
    {
        return $this->db->table('users')->where($field, $value)
            ->where('id !=', $userId)->countAllResults() > 0;
    }
}
