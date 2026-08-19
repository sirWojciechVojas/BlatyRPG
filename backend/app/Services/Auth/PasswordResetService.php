<?php

namespace App\Services\Auth;

use App\Models\PasswordResetTokenModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use Config\Services;

final class PasswordResetService
{
    private $db;
    private $tokens;
    private $users;
    private $accounts;
    private $sendMail;

    public function __construct(
        ?BaseConnection $db = null,
        ?PasswordResetTokenModel $tokens = null,
        ?UserModel $users = null,
        ?AuthAccountService $accounts = null,
        ?callable $sendMail = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->tokens = $tokens ?: new PasswordResetTokenModel($this->db);
        $this->users = $users ?: new UserModel($this->db);
        $this->accounts = $accounts ?: new AuthAccountService($this->db, new UserModel($this->db));
        $this->sendMail = $sendMail ?: [$this, 'sendResetEmail'];
    }

    public function request(string $email, ?string $ip = null): void
    {
        $user = $this->users->where('email', strtolower($email))->first();
        if (!$user) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->tokens->where('user_id', (int) $user['id'])
            ->where('used_at', null)->set(['used_at' => $now])->update();
        $rawToken = bin2hex(random_bytes(32));
        $inserted = $this->tokens->insert([
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => date('Y-m-d H:i:s', time() + $this->ttl()),
            'request_ip_hash' => $ip ? hash('sha256', $ip) : null,
        ]);
        if (!$inserted) {
            log_message('error', 'Password reset token could not be stored for user id {userId}.', [
                'userId' => (int) $user['id'],
            ]);
            return;
        }
        $sent = (bool) ($this->sendMail)((string) $user['email'], $rawToken);
        if (!$sent) {
            $this->tokens->update((int) $this->tokens->getInsertID(), ['used_at' => $now]);
            log_message('error', 'Password reset email could not be sent for user id {userId}.', [
                'userId' => (int) $user['id'],
            ]);
        }
    }

    public function confirm(
        string $rawToken,
        string $newPassword,
        ?string $ip,
        ?string $userAgent
    ): array {
        $now = date('Y-m-d H:i:s');
        $reset = $this->tokens->where('token_hash', hash('sha256', $rawToken))
            ->where('used_at', null)->where('expires_at >', $now)->first();
        if (!$reset) {
            throw $this->invalidToken();
        }
        $user = $this->users->find((int) $reset['user_id']);
        if (!$user) {
            throw $this->invalidToken();
        }

        $this->db->transBegin();
        try {
            $this->db->table('password_reset_tokens')->where('id', (int) $reset['id'])
                ->where('used_at', null)->where('expires_at >', $now)
                ->update(['used_at' => $now, 'updated_at' => $now]);
            if ($this->db->affectedRows() !== 1) {
                throw $this->invalidToken();
            }
            $result = $this->accounts->replacePasswordAndSessions(
                $user,
                $newPassword,
                $ip,
                $userAgent
            );
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new AuthException('password_write_failed', 'Password could not be changed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $result;
    }

    public function sendResetEmail(string $email, string $token): bool
    {
        $baseUrl = trim((string) (getenv('PASSWORD_RESET_URL') ?: 'https://localhost/reset-password'));
        if (!preg_match('#^https://[^\s]+$#', $baseUrl)
            && !preg_match('#^http://(?:localhost|127\.0\.0\.1)(?::\d+)?(?:/[^\s]*)?$#', $baseUrl)) {
            return false;
        }
        $separator = strpos($baseUrl, '?') === false ? '?' : '&';
        $link = $baseUrl . $separator . 'token=' . rawurlencode($token);
        $emailService = Services::email();
        $emailService->setTo($email);
        $emailService->setSubject('Blaty RPG password reset');
        $emailService->setMessage(
            "A password reset was requested for your Blaty RPG account.\n\n"
            . "Open this one-time link before it expires:\n{$link}\n\n"
            . "If you did not request this change, ignore this message."
        );
        return $emailService->send(false);
    }

    private function ttl(): int
    {
        $ttl = filter_var(getenv('PASSWORD_RESET_TIME_TO_LIVE'), FILTER_VALIDATE_INT);
        return $ttl !== false && $ttl >= 300 && $ttl <= 3600 ? (int) $ttl : 1800;
    }

    private function invalidToken(): AuthException
    {
        return new AuthException(
            'invalid_reset_token',
            'Password reset token is invalid or expired.',
            422
        );
    }
}
