<?php

namespace App\Services\Auth;

use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;

final class OAuthFlowService
{
    private const STATE_TTL = 600;
    private const CODE_TTL = 120;

    private $db;
    private $users;
    private $accounts;

    public function __construct(
        ?BaseConnection $db = null,
        ?UserModel $users = null,
        ?AuthAccountService $accounts = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->users = $users ?: new UserModel($this->db);
        $this->accounts = $accounts ?: new AuthAccountService($this->db, $this->users);
    }

    public function createState(string $provider, string $intent, ?int $userId): array
    {
        if (!in_array($intent, ['login', 'link'], true)
            || ($intent === 'link' && (int) $userId < 1)) {
            throw new AuthException('oauth_intent_invalid', 'OAuth intent is invalid.', 400);
        }
        $state = $this->randomToken();
        $browserToken = $this->randomToken();
        $inserted = $this->db->table('oauth_authorization_states')->insert([
            'state_hash' => hash('sha256', $state),
            'browser_token_hash' => hash('sha256', $browserToken),
            'provider' => $provider,
            'intent' => $intent,
            'user_id' => $intent === 'link' ? (int) $userId : null,
            'expires_at' => date('Y-m-d H:i:s', time() + self::STATE_TTL),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$inserted) {
            throw new AuthException('oauth_state_failed', 'OAuth flow could not be started.', 500);
        }
        return ['state' => $state, 'browserToken' => $browserToken];
    }

    public function consumeState(string $provider, string $state, string $browserToken): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $state)
            || !preg_match('/^[A-Za-z0-9_-]{43}$/', $browserToken)) {
            throw new AuthException('oauth_state_invalid', 'OAuth state is invalid or expired.', 400);
        }
        $record = $this->db->table('oauth_authorization_states')
            ->where('state_hash', hash('sha256', $state))
            ->where('browser_token_hash', hash('sha256', $browserToken))
            ->where('provider', $provider)
            ->where('consumed_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->get()->getRowArray();
        if (!$record) {
            throw new AuthException('oauth_state_invalid', 'OAuth state is invalid or expired.', 400);
        }
        $consumed = $this->db->table('oauth_authorization_states')
            ->where('id', (int) $record['id'])
            ->where('consumed_at', null)
            ->update(['consumed_at' => date('Y-m-d H:i:s')]);
        if (!$consumed || $this->db->affectedRows() !== 1) {
            throw new AuthException('oauth_state_invalid', 'OAuth state is invalid or expired.', 400);
        }
        return $record;
    }

    public function loginOrCreate(array $identity): array
    {
        $linked = $this->identityUser($identity['provider'], $identity['subject']);
        if ($linked) {
            return $this->accounts->activeUser((int) $linked['user_id']);
        }
        if (empty($identity['email_verified']) || empty($identity['email'])) {
            throw new AuthException(
                'oauth_email_required',
                'A verified provider email is required to create an account.',
                422
            );
        }
        $existing = $this->db->table('users')->where('email', $identity['email'])->get()->getRowArray();
        if ($existing) {
            throw new AuthException(
                'oauth_link_required',
                'Sign in with your password and link this provider in account security.',
                409
            );
        }

        $this->db->transBegin();
        try {
            $inserted = $this->users->insert([
                'username' => $this->availableUsername($identity),
                'email' => $identity['email'],
                'password_hash' => bin2hex(random_bytes(32)),
                'role' => UserRole::USER,
                'avatar_url' => $identity['avatar_url'] ?? null,
            ]);
            if (!$inserted) {
                throw new AuthException('oauth_account_failed', 'OAuth account could not be created.', 422);
            }
            $userId = (int) $this->users->getInsertID();
            $this->insertIdentity($userId, $identity);
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new AuthException('oauth_account_failed', 'OAuth account could not be created.', 500);
            }
            return $this->accounts->activeUser($userId);
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof AuthException) {
                throw $exception;
            }
            throw new AuthException('oauth_account_failed', 'OAuth account could not be created.', 409);
        }
    }

    public function link(int $userId, array $identity): array
    {
        $this->accounts->activeUser($userId);
        $linked = $this->identityUser($identity['provider'], $identity['subject']);
        if ($linked) {
            if ((int) $linked['user_id'] !== $userId) {
                throw new AuthException('oauth_identity_in_use', 'This provider account is already linked.', 409);
            }
            return $this->identities($userId);
        }
        $sameProvider = $this->db->table('oauth_identities')
            ->where('user_id', $userId)->where('provider', $identity['provider'])
            ->get()->getRowArray();
        if ($sameProvider) {
            throw new AuthException('oauth_provider_already_linked', 'A provider account is already linked.', 409);
        }
        $this->insertIdentity($userId, $identity);
        return $this->identities($userId);
    }

    public function identities(int $userId): array
    {
        $rows = $this->db->table('oauth_identities')
            ->select('provider, provider_email, created_at')
            ->where('user_id', $userId)->orderBy('provider', 'ASC')->get()->getResultArray();
        return array_map(static function (array $row): array {
            return [
                'provider' => (string) $row['provider'],
                'email' => $row['provider_email'] ?: null,
                'linkedAt' => $row['created_at'] ?? null,
            ];
        }, $rows);
    }

    public function issueLoginCode(int $userId): string
    {
        $code = $this->randomToken();
        $inserted = $this->db->table('oauth_login_codes')->insert([
            'code_hash' => hash('sha256', $code),
            'user_id' => $userId,
            'expires_at' => date('Y-m-d H:i:s', time() + self::CODE_TTL),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$inserted) {
            throw new AuthException('oauth_exchange_failed', 'OAuth login could not be completed.', 500);
        }
        return $code;
    }

    public function consumeLoginCode(string $code): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $code)) {
            throw new AuthException('oauth_code_invalid', 'OAuth login code is invalid or expired.', 400);
        }
        $record = $this->db->table('oauth_login_codes')
            ->where('code_hash', hash('sha256', $code))
            ->where('consumed_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->get()->getRowArray();
        if (!$record) {
            throw new AuthException('oauth_code_invalid', 'OAuth login code is invalid or expired.', 400);
        }
        $consumed = $this->db->table('oauth_login_codes')
            ->where('id', (int) $record['id'])->where('consumed_at', null)
            ->update(['consumed_at' => date('Y-m-d H:i:s')]);
        if (!$consumed || $this->db->affectedRows() !== 1) {
            throw new AuthException('oauth_code_invalid', 'OAuth login code is invalid or expired.', 400);
        }
        return $this->accounts->activeUser((int) $record['user_id']);
    }

    private function insertIdentity(int $userId, array $identity): void
    {
        $inserted = $this->db->table('oauth_identities')->insert([
            'user_id' => $userId,
            'provider' => $identity['provider'],
            'provider_subject' => $identity['subject'],
            'provider_email' => $identity['email'] ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$inserted) {
            throw new AuthException('oauth_link_failed', 'Provider account could not be linked.', 409);
        }
    }

    private function identityUser(string $provider, string $subject): ?array
    {
        return $this->db->table('oauth_identities')
            ->where('provider', $provider)->where('provider_subject', $subject)
            ->get()->getRowArray() ?: null;
    }

    private function availableUsername(array $identity): string
    {
        $base = trim((string) ($identity['name'] ?? ''));
        $base = preg_replace('/[\x00-\x1F\x7F]/u', '', $base) ?: '';
        if (mb_strlen($base) < 3) {
            $local = explode('@', (string) $identity['email'], 2)[0] ?? '';
            $base = preg_replace('/[^\pL\pN_.-]+/u', '', $local) ?: '';
        }
        if (mb_strlen($base) < 3) {
            $base = ucfirst($identity['provider']) . ' user';
        }
        $base = mb_substr($base, 0, 90);
        $candidate = $base;
        for ($suffix = 0; $suffix < 100; $suffix++) {
            if ($this->db->table('users')->where('username', $candidate)->countAllResults() === 0) {
                return $candidate;
            }
            $candidate = mb_substr($base, 0, 92) . '-' . ($suffix + 2);
        }
        return mb_substr($base, 0, 83) . '-' . bin2hex(random_bytes(4));
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
