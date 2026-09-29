<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Journal\HeroJournalException;
use App\Services\Journal\HeroJournalService;
use CodeIgniter\API\ResponseTrait;

class HeroJournalController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $journal;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->journal = new HeroJournalService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $characterId = $this->positiveId(
                $this->request->getGet('characterId') ?? $this->request->getGet('character_id'),
                'characterId'
            );
            $archived = $this->booleanQuery('archived');
            return $this->journal->list((int) $campaignId, $characterId, $this->auth(), [
                'type' => $this->request->getGet('type'),
                'status' => $this->request->getGet('status'),
                'archived' => $archived ?? false,
            ]);
        });
    }

    public function show($campaignId = null, $entryId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId): array {
            return $this->journal->show((int) $campaignId, (int) $entryId, $this->auth());
        });
    }

    public function create($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->journal->create((int) $campaignId, $this->auth(), $this->jsonPayload());
        }, 201);
    }

    public function update($campaignId = null, $entryId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId): array {
            return $this->journal->update(
                (int) $campaignId,
                (int) $entryId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
    }

    public function archive($campaignId = null, $entryId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId): array {
            $payload = $this->jsonPayload();
            if (!array_key_exists('archived', $payload) || !is_bool($payload['archived'])) {
                throw new HeroJournalException(
                    'validation_failed',
                    'Archived must be boolean.',
                    422,
                    ['archived' => 'A boolean value is required.']
                );
            }
            return $this->journal->setArchived(
                (int) $campaignId,
                (int) $entryId,
                $this->auth(),
                $payload['archived'],
                $this->positiveId($payload['revision'] ?? null, 'revision')
            );
        });
    }

    public function delete($campaignId = null, $entryId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId): array {
            $payload = $this->jsonPayload();
            return $this->journal->delete(
                (int) $campaignId,
                (int) $entryId,
                $this->auth(),
                $this->positiveId($payload['revision'] ?? null, 'revision')
            );
        });
    }

    public function addChecklistItem($campaignId = null, $entryId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId): array {
            return $this->journal->addChecklistItem(
                (int) $campaignId,
                (int) $entryId,
                $this->auth(),
                $this->jsonPayload()
            );
        }, 201);
    }

    public function updateChecklistItem($campaignId = null, $entryId = null, $itemId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId, $itemId): array {
            return $this->journal->updateChecklistItem(
                (int) $campaignId,
                (int) $entryId,
                (int) $itemId,
                $this->auth(),
                $this->jsonPayload()
            );
        });
    }

    public function deleteChecklistItem($campaignId = null, $entryId = null, $itemId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId, $itemId): array {
            return $this->journal->deleteChecklistItem(
                (int) $campaignId,
                (int) $entryId,
                (int) $itemId,
                $this->auth()
            );
        });
    }

    public function addRelation($campaignId = null, $entryId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId): array {
            return $this->journal->addRelation(
                (int) $campaignId,
                (int) $entryId,
                $this->auth(),
                $this->jsonPayload()
            );
        }, 201);
    }

    public function deleteRelation($campaignId = null, $entryId = null, $relationId = null)
    {
        return $this->execute(function () use ($campaignId, $entryId, $relationId): array {
            return $this->journal->deleteRelation(
                (int) $campaignId,
                (int) $entryId,
                (int) $relationId,
                $this->auth()
            );
        });
    }

    private function auth(): array
    {
        return $this->authContext->resolveFromRequest($this->request);
    }

    private function jsonPayload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new HeroJournalException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload) || array_is_list($payload)) {
            throw new HeroJournalException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function positiveId($value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HeroJournalException(
                'validation_failed',
                'A valid identifier is required.',
                422,
                [$field => 'A positive integer is required.']
            );
        }
        return (int) $id;
    }

    private function booleanQuery(string $field): ?bool
    {
        $raw = $this->request->getGet($field);
        if ($raw === null || $raw === '') {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($value === null) {
            throw new HeroJournalException(
                'validation_failed',
                'Journal filter is invalid.',
                422,
                [$field => 'A boolean value is required.']
            );
        }
        return $value;
    }

    private function execute(callable $operation, int $successStatus = 200)
    {
        try {
            return $this->respond($operation(), $successStatus);
        } catch (HeroJournalException $exception) {
            $payload = [
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
            ];
            if ($exception->details()) {
                $payload['errors'] = $exception->details();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
