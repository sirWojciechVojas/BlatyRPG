<?php

namespace App\Services\Chat;

use App\Models\CampaignChatMessageModel;
use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;

class CampaignChatService
{
    private $db;
    private $messages;
    private $campaigns;
    private $members;
    private $users;
    private $policy;
    private $validator;
    private $pagination;
    private $presenter;
    private $rateLimiter;
    private $writeLock;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignChatMessageModel $messages = null,
        ?CampaignModel $campaigns = null,
        ?CampaignMemberModel $members = null,
        ?UserModel $users = null,
        ?CampaignChatAccessPolicy $policy = null,
        ?CampaignChatMessageValidator $validator = null,
        ?CampaignChatPagination $pagination = null,
        ?CampaignChatMessagePresenter $presenter = null,
        ?CampaignChatRateLimiter $rateLimiter = null,
        ?CampaignChatWriteLock $writeLock = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->messages = $messages ?: new CampaignChatMessageModel($this->db);
        $this->campaigns = $campaigns ?: new CampaignModel($this->db);
        $this->members = $members ?: new CampaignMemberModel($this->db);
        $this->users = $users ?: new UserModel($this->db);
        $this->policy = $policy ?: new CampaignChatAccessPolicy();
        $this->validator = $validator ?: new CampaignChatMessageValidator();
        $this->pagination = $pagination ?: new CampaignChatPagination();
        $this->presenter = $presenter ?: new CampaignChatMessagePresenter();
        $this->rateLimiter = $rateLimiter ?: new CampaignChatRateLimiter($this->db);
        $this->writeLock = $writeLock ?: new CampaignChatWriteLock($this->db);
    }

    public function list(int $campaignId, array $auth, array $query): array
    {
        $access = $this->authorize($campaignId, $auth, 'canRead');
        $page = $this->pagination->parse($query);
        $builder = $this->messages->where('campaign_id', $campaignId);
        $direction = 'DESC';
        $mode = 'latest';
        if ($page['afterId'] !== null) {
            $builder->where('id >', $page['afterId']);
            $direction = 'ASC';
            $mode = 'after';
        } elseif ($page['beforeId'] !== null) {
            $builder->where('id <', $page['beforeId']);
            $mode = 'before';
        }

        $rows = $builder->orderBy('id', $direction)->findAll($page['limit'] + 1);
        $hasExtra = count($rows) > $page['limit'];
        if ($hasExtra) {
            $rows = array_slice($rows, 0, $page['limit']);
        }
        if ($direction === 'DESC') {
            $rows = array_reverse($rows);
        }

        $items = array_map(function (array $row): array {
            return $this->presenter->present($row);
        }, $rows);
        $deliveredRevision = $items
            ? (int) $items[count($items) - 1]['revision']
            : ($mode === 'after' ? (int) $page['afterId'] : 0);

        return [
            'items' => $items,
            'pagination' => [
                'limit' => $page['limit'],
                'beforeId' => $items ? (int) $items[0]['id'] : null,
                'afterId' => $items ? (int) $items[count($items) - 1]['id'] : null,
                'hasMoreBefore' => $mode !== 'after' && $hasExtra,
                'hasMoreAfter' => $mode === 'after' && $hasExtra,
            ],
            'capabilities' => $access['capabilities'],
            'sync' => [
                'transport' => 'websocket',
                'latestRevision' => $deliveredRevision,
            ],
        ];
    }

    public function send(int $campaignId, array $auth, array $payload): array
    {
        // Preflight authorization keeps auth failures ahead of payload details.
        $access = $this->authorize($campaignId, $auth, 'canSend');
        $validated = $this->validator->validate($payload);
        if (!$validated['valid']) {
            throw new CampaignChatException(
                'validation_failed',
                'Chat message payload is invalid.',
                422,
                $validated['errors']
            );
        }

        $userId = (int) $access['auth']['user_id'];
        $data = $validated['data'];
        $this->db->transBegin();
        try {
            $this->writeLock->lockCampaign($campaignId);
            $this->rateLimiter->lockSender($userId);
            // Membership and the current database role may have changed after preflight.
            $access = $this->authorize($campaignId, $auth, 'canSend');
            $existing = $this->findByNonce($campaignId, $userId, $data['client_nonce']);
            if ($existing) {
                $result = $this->duplicateResult(
                    $existing,
                    $data['body'],
                    $access['capabilities']
                );
            } else {
                $this->rateLimiter->assertWithinLimit($campaignId, $userId);
                $result = $this->insertMessage(
                    $campaignId,
                    $userId,
                    (string) $access['user']['username'],
                    $data,
                    $access['capabilities']
                );
            }
            if (!$this->db->transCommit()) {
                throw new CampaignChatException(
                    'message_write_failed',
                    'Chat message could not be committed.',
                    500
                );
            }
            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function insertMessage(
        int $campaignId,
        int $userId,
        string $authorName,
        array $data,
        array $capabilities
    ): array {
        $inserted = $this->messages->insert([
            'campaign_id' => $campaignId,
            'author_user_id' => $userId,
            'author_name' => $authorName,
            'body' => $data['body'],
            'message_type' => 'text',
            'client_nonce' => $data['client_nonce'],
            'metadata_json' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]) !== false;
        if (!$inserted) {
            throw new CampaignChatException('message_write_failed', 'Chat message could not be saved.', 500);
        }
        $message = $this->messages->find((int) $this->messages->getInsertID());
        if (!$message) {
            throw new CampaignChatException('message_write_failed', 'Chat message could not be read.', 500);
        }

        return [
            'message' => $this->presenter->present($message),
            'capabilities' => $capabilities,
            'duplicate' => false,
        ];
    }

    private function authorize(int $campaignId, array $auth, string $capability): array
    {
        if ($campaignId < 1) {
            throw new CampaignChatException('campaign_not_found', 'Campaign was not found.', 404);
        }
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new CampaignChatException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->users->where('id', $userId)->where('deleted_at', null)->first();
        if (!$user) {
            throw new CampaignChatException('unauthorized', 'Authentication is required.', 401);
        }
        $auth['role'] = strtolower((string) ($user['role'] ?? 'user'));
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            throw new CampaignChatException('campaign_not_found', 'Campaign was not found.', 404);
        }
        $membership = $this->members->where('campaign_id', $campaignId)
            ->where('user_id', $userId)->first();
        $capabilities = $this->policy->evaluate($auth, $campaign, $membership);
        if (empty($capabilities[$capability])) {
            throw new CampaignChatException('forbidden', 'Campaign chat access was denied.', 403);
        }
        return compact('auth', 'user', 'campaign', 'membership', 'capabilities');
    }

    private function findByNonce(int $campaignId, int $userId, ?string $nonce): ?array
    {
        if ($nonce === null) {
            return null;
        }
        return $this->messages->where('campaign_id', $campaignId)
            ->where('author_user_id', $userId)->where('client_nonce', $nonce)->first();
    }

    private function duplicateResult(
        array $existing,
        string $body,
        array $capabilities
    ): array {
        if ((string) $existing['body'] !== $body) {
            throw new CampaignChatException(
                'nonce_conflict',
                'Client nonce has already been used for another message.',
                409
            );
        }
        return [
            'message' => $this->presenter->present($existing),
            'capabilities' => $capabilities,
            'duplicate' => true,
        ];
    }
}
