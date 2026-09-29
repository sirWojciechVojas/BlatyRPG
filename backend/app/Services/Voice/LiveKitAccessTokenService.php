<?php

namespace App\Services\Voice;

use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;
use Firebase\JWT\JWT;

/** Issues short-lived, campaign-scoped LiveKit join credentials. */
final class LiveKitAccessTokenService
{
    private const DEFAULT_TTL = 300;
    private const MAX_TTL = 900;

    private $guard;
    private $db;
    private $environment;

    public function __construct(
        ?CampaignGuardService $guard = null,
        ?BaseConnection $db = null,
        ?callable $environment = null
    ) {
        $this->guard = $guard ?: new CampaignGuardService();
        $this->db = $db ?: \Config\Database::connect();
        $this->environment = $environment ?: static function (string $key) {
            return getenv($key);
        };
    }

    public function issue(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $configuration = $this->configuration();
        $now = time();
        $authExpiry = (int) (
            $context['auth']['expires_at']
            ?? $context['auth']['token_expires_at']
            ?? $context['auth']['exp']
            ?? 0
        );
        if ($authExpiry <= $now) {
            throw new CampaignException('unauthorized', 'Authentication has expired.', 401);
        }
        $expiresAt = min($now + $configuration['ttl'], $authExpiry);
        if ($expiresAt <= $now + 5) {
            throw new CampaignException('unauthorized', 'Authentication is about to expire.', 401);
        }

        $userId = (int) $context['auth']['user_id'];
        $character = $this->participantCharacter($campaignId, $userId);
        $nickname = trim((string) ($context['user']['username'] ?? ''));
        $avatar = (string) (
            $character['avatar_url']
            ?? $character['avatar']
            ?? $context['user']['avatar_url']
            ?? ''
        );
        $metadata = [
            'userId' => $userId,
            'nickname' => $nickname,
            'characterId' => $character ? (int) $character['id'] : null,
            'role' => (string) $context['accessRole'],
            'avatar' => $avatar,
        ];
        $roomName = 'campaign_' . (int) $context['campaign']['id'];
        $identity = 'user_' . $userId;
        $payload = [
            'iss' => $configuration['apiKey'],
            'sub' => $identity,
            'name' => $nickname,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'nbf' => $now - 1,
            'exp' => $expiresAt,
            'video' => [
                'room' => $roomName,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => false,
            ],
        ];

        $this->guard->touchActivity($campaignId);
        return [
            'serverUrl' => $configuration['url'],
            'token' => JWT::encode($payload, $configuration['apiSecret'], 'HS256'),
            'expiresAt' => $expiresAt,
            'expiresIn' => $expiresAt - $now,
            'roomName' => $roomName,
            'identity' => $identity,
            'participant' => $metadata,
        ];
    }

    private function participantCharacter(int $campaignId, int $userId): ?array
    {
        if (!$this->db->tableExists('character_campaigns')) {
            return $this->db->table('characters')
                ->where('campaign_id', $campaignId)
                ->where('user_id', $userId)
                ->orderBy('id', 'ASC')->get(1)->getRowArray() ?: null;
        }
        return $this->db->table('characters characters')
            ->select('characters.id, characters.name, characters.avatar_url, characters.avatar')
            ->join('character_campaigns assignments', 'assignments.character_id = characters.id', 'inner')
            ->where('assignments.campaign_id', $campaignId)
            ->where('characters.user_id', $userId)
            ->orderBy('characters.id', 'ASC')->get(1)->getRowArray() ?: null;
    }

    private function configuration(): array
    {
        $url = trim((string) call_user_func($this->environment, 'LIVEKIT_URL'));
        $apiKey = trim((string) call_user_func($this->environment, 'LIVEKIT_API_KEY'));
        $apiSecret = trim((string) call_user_func($this->environment, 'LIVEKIT_API_SECRET'));
        $parts = $url !== '' ? parse_url($url) : false;
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['ws', 'wss'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !preg_match('/^[A-Za-z0-9_-]{3,128}$/', $apiKey)
            || strlen($apiSecret) < 16) {
            throw new CampaignException(
                'voice_unavailable',
                'LiveKit is not configured.',
                503
            );
        }
        $configuredTtl = filter_var(
            call_user_func($this->environment, 'LIVEKIT_TOKEN_TTL'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 30, 'max_range' => self::MAX_TTL]]
        );
        return [
            'url' => rtrim($url, '/'),
            'apiKey' => $apiKey,
            'apiSecret' => $apiSecret,
            'ttl' => $configuredTtl === false ? self::DEFAULT_TTL : (int) $configuredTtl,
        ];
    }
}
