<?php

namespace App\Services\MapBuilder;

use App\Services\Campaign\CampaignAccessService;
use CodeIgniter\Database\BaseConnection;

final class MapBuilderService
{
    private const LOCK_SECONDS = 120;
    private const REVISION_LIST_LIMIT = 50;

    private $db;
    private $access;
    private $validator;
    private $assets;
    private $providers;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignAccessService $access = null,
        ?MapDocumentValidator $validator = null,
        ?MapAssetStorage $assets = null,
        ?MapAiProviderRegistry $providers = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->access = $access ?: new CampaignAccessService();
        $this->validator = $validator ?: new MapDocumentValidator();
        $this->assets = $assets ?: new MapAssetStorage($this->db);
        $this->providers = $providers ?: new MapAiProviderRegistry();
    }

    public function listProjects(int $campaignId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        $rows = $this->db->table('map_projects')->where('campaign_id', $campaignId)
            ->where('deleted_at', null)->orderBy('updated_at', 'DESC')->get()->getResultArray();
        return [
            'items' => array_map(fn (array $row): array => $this->presentProject($row), $rows),
            'starterAssetCount' => count($this->validator->starterAssetIds()),
        ];
    }

    public function getProject(int $campaignId, int $mapId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        $project = $this->project($campaignId, $mapId);
        $revision = $this->revision($mapId, (int) $project['current_revision']);
        return [
            'map' => $this->presentProject($project),
            'revision' => $this->presentRevision($revision, true),
            'lock' => $this->presentLock($project),
        ];
    }

    public function createProject(int $campaignId, array $auth, array $payload): array
    {
        $this->authorize($campaignId, $auth);
        $document = $this->document($campaignId, $payload['document'] ?? null);
        $name = $this->name($payload['name'] ?? null);
        $sceneId = $this->sceneId($campaignId, $payload['sceneId'] ?? null);
        $editorId = $this->editorId($payload);
        $userId = (int) ($auth['user_id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('map_projects')->insert([
                'campaign_id' => $campaignId,
                'scene_id' => $sceneId,
                'name' => $name,
                'is_template' => !empty($payload['isTemplate']) ? 1 : 0,
                'current_revision' => 1,
                'lock_user_id' => $userId,
                'lock_session_id' => (int) ($auth['session_id'] ?? 0) ?: null,
                'lock_client_id' => $editorId,
                'lock_expires_at' => date('Y-m-d H:i:s', time() + self::LOCK_SECONDS),
                'created_by_user_id' => $userId ?: null,
                'updated_by_user_id' => $userId ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $mapId = (int) $this->db->insertID();
            $this->insertRevision($mapId, 1, $document, $userId, 'Utworzenie projektu');
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $this->getProject($campaignId, $mapId, $auth);
    }

    public function saveProject(int $campaignId, int $mapId, array $auth, array $payload): array
    {
        $this->authorize($campaignId, $auth);
        $project = $this->project($campaignId, $mapId);
        $this->assertLock($project, $auth, $this->editorId($payload));
        $baseRevision = filter_var($payload['baseRevision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($baseRevision === false) {
            throw new MapBuilderException('validation_failed', 'Base revision is required.', 422);
        }
        if ((int) $project['current_revision'] !== (int) $baseRevision) {
            throw new MapBuilderException('map_revision_conflict', 'Map changed since it was loaded.', 409, [
                'currentRevision' => (int) $project['current_revision'],
            ]);
        }
        $document = $this->document($campaignId, $payload['document'] ?? null);
        $name = $this->name($payload['name'] ?? $project['name']);
        $sceneId = array_key_exists('sceneId', $payload)
            ? $this->sceneId($campaignId, $payload['sceneId'])
            : ($project['scene_id'] !== null ? (int) $project['scene_id'] : null);
        $next = (int) $baseRevision + 1;
        $userId = (int) ($auth['user_id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('map_projects')->set([
                'name' => $name,
                'scene_id' => $sceneId,
                'is_template' => !empty($payload['isTemplate']) ? 1 : 0,
                'updated_by_user_id' => $userId ?: null,
                'updated_at' => $now,
                'lock_expires_at' => date('Y-m-d H:i:s', time() + self::LOCK_SECONDS),
            ])->set('current_revision', $next)
                ->where('id', $mapId)->where('campaign_id', $campaignId)
                ->where('current_revision', (int) $baseRevision)->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) {
                throw new MapBuilderException('map_revision_conflict', 'Map changed while it was being saved.', 409);
            }
            $this->insertRevision($mapId, $next, $document, $userId, 'Autosave / zapis ręczny');
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $this->getProject($campaignId, $mapId, $auth);
    }

    public function acquireLock(int $campaignId, int $mapId, array $auth, string $editorId): array
    {
        $this->authorize($campaignId, $auth);
        $this->project($campaignId, $mapId);
        $userId = (int) ($auth['user_id'] ?? 0);
        $sessionId = (int) ($auth['session_id'] ?? 0);
        $editorId = $this->editorId(['editorId' => $editorId]);
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + self::LOCK_SECONDS);
        $this->db->table('map_projects')->set([
            'lock_user_id' => $userId,
            'lock_session_id' => $sessionId ?: null,
            'lock_client_id' => $editorId,
            'lock_expires_at' => $expires,
        ])->where('id', $mapId)->where('campaign_id', $campaignId)->where('deleted_at', null)
            ->groupStart()->where('lock_expires_at <', $now)->orWhere('lock_expires_at', null)
            ->orGroupStart()->where('lock_user_id', $userId)->where('lock_session_id', $sessionId ?: null)
            ->where('lock_client_id', $editorId)
            ->groupEnd()->groupEnd()->update();
        $project = $this->project($campaignId, $mapId);
        $acquired = (int) $project['lock_user_id'] === $userId
            && (int) ($project['lock_session_id'] ?? 0) === $sessionId
            && (string) ($project['lock_client_id'] ?? '') === $editorId;
        if (!$acquired) {
            throw new MapBuilderException('map_locked', 'Map is edited by another active session.', 423, [
                'lock' => $this->presentLock($project),
            ]);
        }
        return ['acquired' => true, 'lock' => $this->presentLock($project)];
    }

    public function releaseLock(int $campaignId, int $mapId, array $auth, string $editorId): array
    {
        $this->authorize($campaignId, $auth);
        $this->db->table('map_projects')->set([
            'lock_user_id' => null, 'lock_session_id' => null,
            'lock_client_id' => null, 'lock_expires_at' => null,
        ])->where('id', $mapId)->where('campaign_id', $campaignId)
            ->where('lock_user_id', (int) ($auth['user_id'] ?? 0))
            ->where('lock_session_id', (int) ($auth['session_id'] ?? 0))
            ->where('lock_client_id', $this->editorId(['editorId' => $editorId]))->update();
        return ['released' => true];
    }

    public function listRevisions(int $campaignId, int $mapId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        $this->project($campaignId, $mapId);
        $rows = $this->db->table('map_revisions')->where('map_id', $mapId)
            ->orderBy('revision_number', 'DESC')->limit(self::REVISION_LIST_LIMIT)->get()->getResultArray();
        return ['items' => array_map(fn (array $row): array => $this->presentRevision($row, false), $rows)];
    }

    public function restoreRevision(
        int $campaignId,
        int $mapId,
        int $revisionId,
        array $auth,
        array $payload
    ): array {
        $this->authorize($campaignId, $auth);
        $project = $this->project($campaignId, $mapId);
        $this->assertLock($project, $auth, $this->editorId($payload));
        $source = $this->db->table('map_revisions')->where('map_id', $mapId)
            ->where('id', $revisionId)->get()->getRowArray();
        if (!$source) throw new MapBuilderException('map_revision_not_found', 'Map revision was not found.', 404);
        $document = json_decode((string) $source['document_json'], true);
        if (!is_array($document)) throw new MapBuilderException('map_revision_corrupt', 'Map revision is corrupt.', 500);
        $next = (int) $project['current_revision'] + 1;
        $userId = (int) ($auth['user_id'] ?? 0);
        $this->db->transBegin();
        try {
            $this->insertRevision($mapId, $next, $document, $userId, 'Przywrócenie rewizji #' . (int) $source['revision_number']);
            $this->db->table('map_projects')->set([
                'current_revision' => $next,
                'updated_by_user_id' => $userId ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ])->where('id', $mapId)->where('current_revision', (int) $project['current_revision'])->update();
            if ($this->db->affectedRows() !== 1) {
                throw new MapBuilderException('map_revision_conflict', 'Map changed during restore.', 409);
            }
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $this->getProject($campaignId, $mapId, $auth);
    }

    public function publish(int $campaignId, int $mapId, array $auth, array $payload): array
    {
        $this->authorize($campaignId, $auth);
        $project = $this->project($campaignId, $mapId);
        $this->assertLock($project, $auth, $this->editorId($payload));
        $revisionNumber = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revisionNumber === false || (int) $project['current_revision'] !== (int) $revisionNumber) {
            throw new MapBuilderException('map_revision_conflict', 'Only the current saved revision can be published.', 409);
        }
        $sceneId = $this->sceneId($campaignId, $payload['sceneId'] ?? $project['scene_id']);
        if (!$sceneId) throw new MapBuilderException('scene_required', 'Choose a scene before publishing.', 422);
        $renderUrl = trim((string) ($payload['renderAssetUrl'] ?? ''));
        if (!preg_match('#^/api/campaigns/' . $campaignId . '/scene-assets/[a-f0-9]{32}\.(?:png|jpg|webp)/file$#', $renderUrl)) {
            throw new MapBuilderException('map_render_invalid', 'Published render does not belong to this campaign.', 422);
        }
        $revision = $this->revision($mapId, (int) $revisionNumber);
        $document = json_decode((string) $revision['document_json'], true);
        $validated = $this->validator->validateDocument(
            $document,
            fn (string $assetId): bool => $this->assets->exists($campaignId, $assetId)
        );
        if (!$validated['valid']) {
            throw new MapBuilderException('map_document_invalid', 'Saved map is not publishable.', 422, $validated['errors']);
        }
        $publicLayerIds = [];
        foreach ($document['layers'] ?? [] as $layer) {
            if (($layer['visible'] ?? true) !== false && empty($layer['private'])) {
                $publicLayerIds[(string) ($layer['id'] ?? '')] = true;
            }
        }
        $publicLevelIds = [];
        foreach ($document['levels'] ?? [] as $level) {
            if (($level['visible'] ?? true) !== false) {
                $publicLevelIds[(string) ($level['id'] ?? '')] = true;
            }
        }
        $objects = array_values(array_filter(
            $document['objects'] ?? [],
            static function (array $object) use ($publicLayerIds, $publicLevelIds): bool {
                return isset($publicLayerIds[(string) ($object['layerId'] ?? '')])
                    && (!$publicLevelIds || isset($publicLevelIds[(string) ($object['levelId'] ?? '')]))
                    && empty($object['private']) && ($object['visible'] ?? true) !== false;
            }
        ));
        $walls = $this->wallRows($campaignId, $sceneId, $mapId, (int) $revisionNumber, $objects);
        $lights = $this->lightRows($campaignId, $sceneId, $mapId, (int) $revisionNumber, $objects);
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            foreach (['scene_walls', 'scene_lights', 'scene_tiles'] as $table) {
                $this->db->table($table)->set(['deleted_at' => $now, 'updated_at' => $now])
                    ->where('campaign_id', $campaignId)->where('scene_id', $sceneId)
                    ->where('source_map_id', $mapId)->where('deleted_at', null)->update();
            }
            foreach ($walls as $wall) $this->db->table('scene_walls')->insert($wall);
            foreach ($lights as $light) $this->db->table('scene_lights')->insert($light);
            $grid = is_array($document['grid'] ?? null) ? $document['grid'] : [];
            $this->db->table('scenes')->set([
                'background_url' => $renderUrl,
                'width' => (int) $document['width'],
                'height' => (int) $document['height'],
                'background_color' => (string) ($document['backgroundColor'] ?? '#000000'),
                'grid_type' => in_array(($grid['type'] ?? ''), ['square', 'hex', 'none'], true) ? $grid['type'] : 'square',
                'grid_size' => max(10, (int) ($grid['size'] ?? 100)),
                'grid_distance' => max(0.01, (float) ($grid['distance'] ?? 1)),
                'grid_unit' => mb_substr((string) ($grid['unit'] ?? 'm'), 0, 32),
                'grid_offset_x' => (float) ($grid['offsetX'] ?? 0),
                'grid_offset_y' => (float) ($grid['offsetY'] ?? 0),
                'grid_color' => (string) ($grid['color'] ?? '#000000'),
                'grid_opacity' => max(0, min(1, (float) ($grid['opacity'] ?? 0.35))),
                'updated_at' => $now,
            ])->set('revision', 'revision + 1', false)
                ->where('id', $sceneId)->where('campaign_id', $campaignId)->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) {
                throw new MapBuilderException('scene_not_found', 'Target scene was not found.', 404);
            }
            $this->db->table('map_projects')->set([
                'scene_id' => $sceneId,
                'published_revision' => (int) $revisionNumber,
                'published_at' => $now,
                'updated_at' => $now,
                'updated_by_user_id' => (int) ($auth['user_id'] ?? 0) ?: null,
            ])->where('id', $mapId)->update();
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $scene = $this->db->table('scenes')->where('id', $sceneId)->get()->getRowArray();
        return [
            'published' => true,
            'mapId' => $mapId,
            'mapRevision' => (int) $revisionNumber,
            'sceneId' => $sceneId,
            'sceneRevision' => (int) ($scene['revision'] ?? 0),
            'wallCount' => count($walls),
            'lightCount' => count($lights),
            'preserved' => ['tokens' => true, 'fog' => true],
        ];
    }

    public function listAssets(int $campaignId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        return $this->assets->list($campaignId);
    }

    public function uploadAsset(int $campaignId, array $auth, $file, array $metadata): array
    {
        $this->authorize($campaignId, $auth);
        return $this->assets->upload($campaignId, (int) ($auth['user_id'] ?? 0), $file, $metadata);
    }

    public function importPackage(int $campaignId, array $auth, $file): array
    {
        $this->authorize($campaignId, $auth);
        return $this->assets->importPackage($campaignId, (int) ($auth['user_id'] ?? 0), $file);
    }

    public function downloadAsset(int $campaignId, int $assetId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        return $this->assets->download($campaignId, $assetId);
    }

    public function aiStatus(int $campaignId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        return $this->providers->status() + [
            'dailyCostUnitLimit' => $this->dailyCostLimit(),
            'dailyCostUnitsUsed' => $this->dailyCostUsed($campaignId),
        ];
    }

    public function createAiJob(
        int $campaignId,
        int $mapId,
        array $auth,
        array $payload
    ): array {
        $this->authorize($campaignId, $auth);
        $project = $this->project($campaignId, $mapId);
        $this->assertLock($project, $auth, $this->editorId($payload));
        $status = $this->providers->status();
        if (empty($status['available'])) {
            throw new MapBuilderException('ai_not_configured', 'Map AI provider is not configured.', 503);
        }
        $kind = strtolower(trim((string) ($payload['kind'] ?? '')));
        if (!in_array($kind, ['layout', 'selection', 'generate_asset'], true)) {
            throw new MapBuilderException('validation_failed', 'AI job kind is invalid.', 422);
        }
        $prompt = trim((string) ($payload['prompt'] ?? ''));
        if ($prompt === '' || mb_strlen($prompt) > 4000) {
            throw new MapBuilderException('validation_failed', 'AI prompt must contain 1 to 4000 characters.', 422);
        }
        $baseRevision = (int) ($payload['baseRevision'] ?? 0);
        if ($baseRevision !== (int) $project['current_revision']) {
            throw new MapBuilderException('map_revision_conflict', 'AI requires the current saved revision.', 409);
        }
        $baseRow = $this->revision($mapId, $baseRevision);
        $baseDocument = json_decode((string) $baseRow['document_json'], true);
        if (!is_array($baseDocument)) {
            throw new MapBuilderException('map_revision_corrupt', 'Map revision is corrupt.', 500);
        }
        $idempotency = trim((string) ($payload['idempotencyKey'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $idempotency)) {
            throw new MapBuilderException('validation_failed', 'AI idempotency key is invalid.', 422);
        }
        $userId = (int) ($auth['user_id'] ?? 0);
        $existing = $this->db->table('map_ai_jobs')->where('campaign_id', $campaignId)
            ->where('requested_by_user_id', $userId)->where('idempotency_key', $idempotency)
            ->get()->getRowArray();
        if ($existing) return ['job' => $this->presentAiJob($existing)];
        $cost = $kind === 'generate_asset' ? 10 : 1;
        if ($this->dailyCostUsed($campaignId) + $cost > $this->dailyCostLimit()) {
            throw new MapBuilderException('ai_cost_limit_exceeded', 'Campaign AI daily cost limit is exhausted.', 429);
        }
        $jobId = $this->uuid();
        $input = [
            'baseRevision' => $baseRevision,
            'selection' => $this->aiSelection($payload['selection'] ?? null, $baseDocument, $kind),
        ];
        try {
            $written = $this->db->table('map_ai_jobs')->insert([
                'id' => $jobId,
                'campaign_id' => $campaignId,
                'map_id' => $mapId,
                'requested_by_user_id' => $userId ?: null,
                'idempotency_key' => $idempotency,
                'kind' => $kind,
                'status' => 'queued',
                'prompt' => $prompt,
                'input_json' => json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'provider' => $status['provider'],
                'model' => $kind === 'generate_asset' ? $status['imageModel'] : $status['textModel'],
                'estimated_cost_units' => $cost,
                'actual_cost_units' => 0,
                'cancel_requested' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            $written = false;
        }
        if (!$written) {
            $existing = $this->db->table('map_ai_jobs')->where('campaign_id', $campaignId)
                ->where('requested_by_user_id', $userId)->where('idempotency_key', $idempotency)
                ->get()->getRowArray();
            if ($existing) return ['job' => $this->presentAiJob($existing)];
            throw new MapBuilderException('ai_job_write_failed', 'AI job could not be queued.', 500);
        }
        return ['job' => $this->presentAiJob($this->aiJob($campaignId, $mapId, $jobId))];
    }

    public function getAiJob(int $campaignId, int $mapId, string $jobId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        $row = $this->aiJob($campaignId, $mapId, $jobId);
        return ['job' => $this->presentAiJob($row)];
    }

    public function cancelAiJob(int $campaignId, int $mapId, string $jobId, array $auth): array
    {
        $this->authorize($campaignId, $auth);
        $this->aiJob($campaignId, $mapId, $jobId);
        $this->db->table('map_ai_jobs')->set([
            'cancel_requested' => 1,
            'status' => 'cancelled',
            'finished_at' => date('Y-m-d H:i:s'),
        ])->where('id', $jobId)->whereIn('status', ['queued', 'running'])->update();
        return ['job' => $this->presentAiJob($this->aiJob($campaignId, $mapId, $jobId))];
    }

    public function processNextAiJob(): bool
    {
        $row = $this->db->table('map_ai_jobs')->where('status', 'queued')
            ->orderBy('created_at', 'ASC')->get(1)->getRowArray();
        if (!$row) return false;
        $this->processAiJob((string) $row['id']);
        return true;
    }

    public function processAiJob(string $jobId): void
    {
        $started = date('Y-m-d H:i:s');
        $this->db->table('map_ai_jobs')->set(['status' => 'running', 'started_at' => $started])
            ->where('id', $jobId)->where('status', 'queued')->where('cancel_requested', 0)->update();
        if ($this->db->affectedRows() !== 1) return;
        $job = $this->db->table('map_ai_jobs')->where('id', $jobId)->get()->getRowArray();
        if (!$job) return;
        try {
            $input = json_decode((string) ($job['input_json'] ?? ''), true) ?: [];
            $revision = $this->revision((int) $job['map_id'], (int) ($input['baseRevision'] ?? 0));
            $document = json_decode((string) $revision['document_json'], true);
            if (!is_array($document)) {
                throw new MapBuilderException('map_revision_corrupt', 'Map revision is corrupt.', 500);
            }
            $provider = $this->providers->provider();
            if ($job['kind'] === 'generate_asset') {
                $generated = $provider->generateAsset(['prompt' => $job['prompt']]);
                $asset = $this->assets->storeGenerated(
                    (int) $job['campaign_id'],
                    (int) ($job['requested_by_user_id'] ?? 0),
                    $generated['bytes'],
                    $generated['name'],
                    [
                        'name' => pathinfo($generated['name'], PATHINFO_FILENAME),
                        'category' => 'własne',
                        'tags' => ['AI', 'wygenerowane'],
                        'physicalSize' => ['width' => 1, 'height' => 1, 'unit' => 'm'],
                        'anchor' => ['x' => 0.5, 'y' => 0.5],
                        'provenance' => [
                            'type' => 'ai-generated',
                            'provider' => $provider->name(),
                            'model' => $provider->imageModel(),
                            'revisedPrompt' => $generated['revisedPrompt'],
                        ],
                    ]
                );
                $result = ['asset' => $asset, 'usage' => $generated['usage']];
            } else {
                $custom = $this->assets->list((int) $job['campaign_id']);
                $assetIds = array_merge(
                    $this->validator->starterAssetIds(),
                    array_column($custom['items'], 'id')
                );
                $result = $provider->propose([
                    'kind' => $job['kind'],
                    'prompt' => $job['prompt'],
                    'document' => $document,
                    'selection' => $input['selection'] ?? null,
                    'assetIds' => $assetIds,
                ]);
                $validated = $this->validator->validateAiProposal(
                    $result['proposal'],
                    $document,
                    fn (string $assetId): bool => $this->assets->exists((int) $job['campaign_id'], $assetId)
                );
                if (!$validated['valid']) {
                    throw new MapBuilderException(
                        'ai_validation_failed',
                        'AI proposal failed geometry or asset validation.',
                        422,
                        $validated['errors']
                    );
                }
            }
            $current = $this->db->table('map_ai_jobs')->where('id', $jobId)->get()->getRowArray();
            if (!empty($current['cancel_requested'])) {
                $this->db->table('map_ai_jobs')->set([
                    'status' => 'cancelled', 'finished_at' => date('Y-m-d H:i:s'),
                ])->where('id', $jobId)->update();
                return;
            }
            $this->db->table('map_ai_jobs')->set([
                'status' => 'completed',
                'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'actual_cost_units' => (int) $job['estimated_cost_units'],
                'finished_at' => date('Y-m-d H:i:s'),
            ])->where('id', $jobId)->where('status', 'running')->update();
        } catch (\Throwable $exception) {
            $code = $exception instanceof MapBuilderException ? $exception->errorCode() : 'ai_job_failed';
            $this->db->table('map_ai_jobs')->set([
                'status' => 'failed',
                'error_code' => $code,
                'finished_at' => date('Y-m-d H:i:s'),
            ])->where('id', $jobId)->where('status', 'running')->update();
        }
    }

    private function authorize(int $campaignId, array $auth): void
    {
        $access = $this->access->forCampaign($auth, $campaignId);
        if (!$access['exists']) throw new MapBuilderException('campaign_not_found', 'Campaign was not found.', 404);
        if (!$access['allowed'] || empty($access['capabilities']['canManage'])) {
            throw new MapBuilderException('forbidden', 'Only a campaign manager can use the map builder.', 403);
        }
    }

    private function project(int $campaignId, int $mapId): array
    {
        $row = $this->db->table('map_projects')->where('campaign_id', $campaignId)
            ->where('id', $mapId)->where('deleted_at', null)->get()->getRowArray();
        if (!$row) throw new MapBuilderException('map_not_found', 'Map project was not found.', 404);
        return $row;
    }

    private function revision(int $mapId, int $number): array
    {
        $row = $this->db->table('map_revisions')->where('map_id', $mapId)
            ->where('revision_number', $number)->get()->getRowArray();
        if (!$row) throw new MapBuilderException('map_revision_not_found', 'Map revision was not found.', 404);
        return $row;
    }

    private function document(int $campaignId, $value): array
    {
        if (!is_array($value)) throw new MapBuilderException('validation_failed', 'Map document is required.', 422);
        $validated = $this->validator->validateDocument(
            $value,
            fn (string $assetId): bool => $this->assets->exists($campaignId, $assetId)
        );
        if (!$validated['valid']) {
            throw new MapBuilderException('map_document_invalid', 'Map document is invalid.', 422, $validated['errors']);
        }
        return $value;
    }

    private function name($value): string
    {
        $name = trim((string) $value);
        if ($name === '' || mb_strlen($name) > 150) {
            throw new MapBuilderException('validation_failed', 'Map name must contain 1 to 150 characters.', 422);
        }
        return $name;
    }

    private function sceneId(int $campaignId, $value): ?int
    {
        if ($value === null || $value === '') return null;
        $sceneId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($sceneId === false || $this->db->table('scenes')->where('campaign_id', $campaignId)
            ->where('id', (int) $sceneId)->where('deleted_at', null)->countAllResults() !== 1) {
            throw new MapBuilderException('scene_not_found', 'Scene was not found.', 404);
        }
        return (int) $sceneId;
    }

    private function editorId(array $payload): string
    {
        $editorId = trim((string) ($payload['editorId'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $editorId)) {
            throw new MapBuilderException('validation_failed', 'Editor instance identifier is invalid.', 422);
        }
        return $editorId;
    }

    private function assertLock(array $project, array $auth, string $editorId): void
    {
        $expired = !$project['lock_expires_at'] || strtotime((string) $project['lock_expires_at']) <= time();
        $owned = (int) ($project['lock_user_id'] ?? 0) === (int) ($auth['user_id'] ?? 0)
            && (int) ($project['lock_session_id'] ?? 0) === (int) ($auth['session_id'] ?? 0)
            && (string) ($project['lock_client_id'] ?? '') === $editorId;
        if (!$owned || $expired) {
            throw new MapBuilderException('map_locked', 'An active map editor lock is required.', 423, [
                'lock' => $this->presentLock($project),
            ]);
        }
    }

    private function insertRevision(
        int $mapId,
        int $number,
        array $document,
        int $userId,
        string $summary
    ): void {
        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) throw new MapBuilderException('map_document_invalid', 'Map document could not be encoded.', 422);
        $this->db->table('map_revisions')->insert([
            'map_id' => $mapId,
            'revision_number' => $number,
            'document_json' => $json,
            'document_bytes' => strlen($json),
            'checksum_sha256' => hash('sha256', $json),
            'summary' => mb_substr($summary, 0, 255),
            'created_by_user_id' => $userId ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($this->db->affectedRows() !== 1) {
            throw new MapBuilderException('map_revision_write_failed', 'Map revision could not be saved.', 500);
        }
    }

    private function presentProject(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'campaignId' => (int) $row['campaign_id'],
            'sceneId' => $row['scene_id'] !== null ? (int) $row['scene_id'] : null,
            'name' => (string) $row['name'],
            'isTemplate' => (bool) $row['is_template'],
            'currentRevision' => (int) $row['current_revision'],
            'publishedRevision' => $row['published_revision'] !== null ? (int) $row['published_revision'] : null,
            'publishedAt' => $row['published_at'] ?: null,
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }

    private function presentRevision(array $row, bool $withDocument): array
    {
        $result = [
            'id' => (int) $row['id'],
            'number' => (int) $row['revision_number'],
            'bytes' => (int) $row['document_bytes'],
            'checksum' => (string) $row['checksum_sha256'],
            'summary' => $row['summary'] ?: null,
            'createdAt' => (string) $row['created_at'],
        ];
        if ($withDocument) $result['document'] = json_decode((string) $row['document_json'], true);
        return $result;
    }

    private function presentLock(array $project): ?array
    {
        if (!$project['lock_user_id'] || !$project['lock_expires_at']
            || strtotime((string) $project['lock_expires_at']) <= time()) return null;
        $user = $this->db->table('users')->select('username')->where('id', (int) $project['lock_user_id'])
            ->get()->getRowArray();
        return [
            'ownerUserId' => (int) $project['lock_user_id'],
            'ownerName' => (string) ($user['username'] ?? 'GM'),
            'expiresAt' => (string) $project['lock_expires_at'],
        ];
    }

    private function presentAiJob(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'kind' => (string) $row['kind'],
            'status' => (string) $row['status'],
            'provider' => $row['provider'] ?: null,
            'model' => $row['model'] ?: null,
            'result' => $row['result_json'] ? json_decode((string) $row['result_json'], true) : null,
            'error' => $row['error_code'] ?: null,
            'estimatedCostUnits' => (int) $row['estimated_cost_units'],
            'actualCostUnits' => (int) $row['actual_cost_units'],
            'cancelRequested' => (bool) $row['cancel_requested'],
            'createdAt' => (string) $row['created_at'],
            'startedAt' => $row['started_at'] ?: null,
            'finishedAt' => $row['finished_at'] ?: null,
        ];
    }

    private function aiJob(int $campaignId, int $mapId, string $jobId): array
    {
        if (!preg_match('/^[a-f0-9-]{36}$/', $jobId)) {
            throw new MapBuilderException('ai_job_not_found', 'AI job was not found.', 404);
        }
        $row = $this->db->table('map_ai_jobs')->where('campaign_id', $campaignId)
            ->where('map_id', $mapId)->where('id', $jobId)->get()->getRowArray();
        if (!$row) throw new MapBuilderException('ai_job_not_found', 'AI job was not found.', 404);
        return $row;
    }

    private function dailyCostLimit(): int
    {
        return max(0, min(100000, (int) (getenv('MAP_AI_DAILY_COST_UNITS') ?: 50)));
    }

    private function aiSelection($value, array $document, string $kind): ?array
    {
        if ($kind !== 'selection') return null;
        if (!is_array($value)) {
            throw new MapBuilderException('validation_failed', 'AI selection is required.', 422);
        }
        $knownIds = [];
        foreach ($document['objects'] ?? [] as $object) {
            $knownIds[(string) ($object['id'] ?? '')] = true;
        }
        $objectIds = array_values(array_unique(array_map(
            'strval',
            is_array($value['objectIds'] ?? null) ? $value['objectIds'] : []
        )));
        if (count($objectIds) > 2000) {
            throw new MapBuilderException('validation_failed', 'AI selection is too large.', 422);
        }
        foreach ($objectIds as $objectId) {
            if (!isset($knownIds[$objectId])) {
                throw new MapBuilderException('validation_failed', 'AI selection references an unknown object.', 422);
            }
        }
        $bounds = $value['bounds'] ?? null;
        if ($bounds !== null) {
            if (!is_array($bounds)) {
                throw new MapBuilderException('validation_failed', 'AI selection bounds are invalid.', 422);
            }
            $x = (float) ($bounds['x'] ?? -1);
            $y = (float) ($bounds['y'] ?? -1);
            $right = (float) ($bounds['right'] ?? -1);
            $bottom = (float) ($bounds['bottom'] ?? -1);
            if (!is_finite($x) || !is_finite($y) || !is_finite($right) || !is_finite($bottom)
                || $x < 0 || $y < 0 || $right <= $x || $bottom <= $y
                || $right > (float) ($document['width'] ?? 0)
                || $bottom > (float) ($document['height'] ?? 0)) {
                throw new MapBuilderException('validation_failed', 'AI selection bounds are outside the map.', 422);
            }
            $bounds = ['x' => $x, 'y' => $y, 'right' => $right, 'bottom' => $bottom];
        }
        if (!$objectIds && $bounds === null) {
            throw new MapBuilderException('validation_failed', 'Select objects or an area for the AI edit.', 422);
        }
        return ['objectIds' => $objectIds, 'bounds' => $bounds];
    }

    private function dailyCostUsed(int $campaignId): int
    {
        $row = $this->db->table('map_ai_jobs')
            ->selectSum('estimated_cost_units', 'cost')
            ->where('campaign_id', $campaignId)
            ->where('created_at >=', gmdate('Y-m-d 00:00:00'))
            ->whereIn('status', ['queued', 'running', 'completed'])->get()->getRowArray();
        return (int) ($row['cost'] ?? 0);
    }

    private function wallRows(
        int $campaignId,
        int $sceneId,
        int $mapId,
        int $revision,
        array $objects
    ): array {
        $solid = [];
        $openings = [];
        foreach ($objects as $object) {
            $type = (string) ($object['type'] ?? '');
            if (in_array($type, ['door', 'window'], true)) {
                $openings[] = $this->openingSegment($object);
                continue;
            }
            if (in_array($type, ['wall', 'fence'], true)) {
                $points = $object['points'] ?? [];
                for ($index = 1; $index < count($points); $index++) {
                    $solid[] = [
                        'start' => $this->transformPoint($object, $points[$index - 1]),
                        'end' => $this->transformPoint($object, $points[$index]),
                        'object' => $object,
                    ];
                }
            } elseif ($type === 'room') {
                foreach ($this->roomSegments($object) as $segment) {
                    $solid[] = ['start' => $segment[0], 'end' => $segment[1], 'object' => $object];
                }
            }
        }
        $split = [];
        foreach ($solid as $segment) {
            $parts = [$segment];
            foreach ($openings as $opening) {
                $next = [];
                foreach ($parts as $part) $next = array_merge($next, $this->subtractOpening($part, $opening));
                $parts = $next;
            }
            $split = array_merge($split, $parts);
        }
        $rows = [];
        foreach ($split as $segment) {
            $rows[] = $this->wallRow(
                $campaignId, $sceneId, $mapId, $revision,
                $segment['object'], $segment['start'], $segment['end'], 'wall'
            );
        }
        foreach ($openings as $opening) {
            $rows[] = $this->wallRow(
                $campaignId, $sceneId, $mapId, $revision,
                $opening['object'], $opening['start'], $opening['end'], $opening['object']['type']
            );
        }
        return $rows;
    }

    private function wallRow(
        int $campaignId,
        int $sceneId,
        int $mapId,
        int $revision,
        array $object,
        array $start,
        array $end,
        string $type
    ): array {
        $isWindow = $type === 'window';
        $isDoor = $type === 'door';
        $now = date('Y-m-d H:i:s');
        return [
            'campaign_id' => $campaignId,
            'scene_id' => $sceneId,
            'source_map_id' => $mapId,
            'source_map_revision' => $revision,
            'source_map_object_id' => (string) ($object['id'] ?? ''),
            'name' => $isDoor ? 'Drzwi mapy' : ($isWindow ? 'Okno mapy' : 'Ściana mapy'),
            'type' => $type,
            'x1' => (float) $start['x'], 'y1' => (float) $start['y'],
            'x2' => (float) $end['x'], 'y2' => (float) $end['y'],
            'blocks_movement' => $isWindow ? 1 : 1,
            'blocks_sight' => $isWindow ? 0 : 1,
            'blocks_light' => $isWindow ? 0 : 1,
            'door_state' => ($isDoor || $isWindow) ? (string) ($object['doorState'] ?? 'closed') : null,
            'color' => strtoupper((string) ($object['color'] ?? '#8B8170')),
            'enabled' => 1,
            'hidden' => 0,
            'wall_type' => 'solid',
            'door_type' => $isDoor ? 'door' : ($isWindow ? 'window' : 'none'),
            'restriction_type' => $isWindow ? 'proximity' : 'normal',
            'blocks_sound' => $isWindow ? 0 : 1,
            'proximity_threshold' => 10,
            'player_operable' => ($isDoor || $isWindow) ? 1 : 0,
            'revision' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function lightRows(
        int $campaignId,
        int $sceneId,
        int $mapId,
        int $revision,
        array $objects
    ): array {
        $assetLights = [
            'starter.hearth' => ['#FFB35C', 200, 500, 'torch'],
            'starter.torch' => ['#FFB35C', 250, 600, 'torch'],
            'starter.campfire' => ['#FF9F45', 200, 500, 'flicker'],
        ];
        $rows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($objects as $object) {
            $preset = $assetLights[$object['assetId'] ?? ''] ?? null;
            if (($object['type'] ?? '') !== 'light' && !$preset) continue;
            $config = is_array($object['light'] ?? null) ? $object['light'] : [];
            $lightScale = max(abs((float) ($object['scaleX'] ?? 1)), abs((float) ($object['scaleY'] ?? 1)));
            $lightScale = $lightScale > 0 ? $lightScale : 1;
            $brightBase = (float) ($config['brightRadius'] ?? $preset[1]
                ?? max(100, (float) ($object['width'] ?? 100)));
            $dimBase = (float) ($config['dimRadius'] ?? $preset[2] ?? $brightBase * 2);
            $bright = $brightBase * $lightScale;
            $dim = max($bright, $dimBase * $lightScale);
            $color = strtoupper((string) ($config['color'] ?? $preset[0] ?? '#FFD27A'));
            $rows[] = [
                'campaign_id' => $campaignId,
                'scene_id' => $sceneId,
                'source_map_id' => $mapId,
                'source_map_revision' => $revision,
                'source_map_object_id' => (string) ($object['id'] ?? ''),
                'x' => (float) ($object['x'] ?? 0),
                'y' => (float) ($object['y'] ?? 0),
                'bright_radius' => $bright,
                'dim_radius' => $dim,
                'color' => preg_match('/^#[0-9A-F]{6}$/', $color) ? $color : '#FFD27A',
                'intensity' => 1,
                'opacity' => 1,
                'softness' => 0.65,
                'clarity' => 0,
                'gradual_illumination' => 1,
                'darkness_min' => 0,
                'darkness_max' => 1,
                'source_type' => 'omni',
                'provides_vision' => 0,
                'constrained_by_walls' => 1,
                'animation' => (string) ($config['animation'] ?? $preset[3] ?? 'none'),
                'animation_speed' => 1,
                'animation_intensity' => 0.5,
                'elevation' => 0,
                'enabled' => 1,
                'hidden' => 0,
                'name' => 'Światło mapy',
                'lumens' => 800,
                'direction' => 0,
                'angle' => 360,
                'area_width' => 400,
                'area_height' => 400,
                'animation_reverse' => 0,
                'brightness' => 1,
                'saturation' => 1,
                'contrast' => 1,
                'edge_softness' => 0.5,
                'transition_ratio' => 0.5,
                'revision' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return $rows;
    }

    private function openingSegment(array $object): array
    {
        $length = max(20, (float) ($object['width'] ?? 100) * abs((float) ($object['scaleX'] ?? 1)));
        $angle = deg2rad((float) ($object['rotation'] ?? 0));
        $dx = cos($angle) * $length / 2;
        $dy = sin($angle) * $length / 2;
        return [
            'start' => ['x' => (float) $object['x'] - $dx, 'y' => (float) $object['y'] - $dy],
            'end' => ['x' => (float) $object['x'] + $dx, 'y' => (float) $object['y'] + $dy],
            'object' => $object,
        ];
    }

    private function roomSegments(array $object): array
    {
        return array_map(function (array $segment) use ($object): array {
            return [
                $this->transformPoint($object, $segment[0]),
                $this->transformPoint($object, $segment[1]),
            ];
        }, $this->rawRoomSegments($object));
    }

    private function rawRoomSegments(array $object): array
    {
        if (($object['shape'] ?? '') === 'composite' && is_array($object['booleanParts'] ?? null)) {
            return $this->compositeRoomSegments($object['booleanParts']);
        }
        $points = $object['points'] ?? [];
        if (($object['shape'] ?? '') === 'polygon' && count($points) >= 3) {
            $segments = [];
            for ($index = 0; $index < count($points); $index++) {
                $segments[] = [$points[$index], $points[($index + 1) % count($points)]];
            }
            return $segments;
        }
        $x = (float) ($object['x'] ?? 0);
        $y = (float) ($object['y'] ?? 0);
        $width = (float) ($object['width'] ?? 100);
        $height = (float) ($object['height'] ?? 100);
        if (($object['shape'] ?? '') === 'circle') {
            $segments = [];
            $previous = null;
            $first = null;
            for ($index = 0; $index < 24; $index++) {
                $angle = 2 * M_PI * $index / 24;
                $point = ['x' => $x + cos($angle) * $width / 2, 'y' => $y + sin($angle) * $height / 2];
                if ($previous) $segments[] = [$previous, $point];
                else $first = $point;
                $previous = $point;
            }
            $segments[] = [$previous, $first];
            return $segments;
        }
        $corners = [
            ['x' => $x - $width / 2, 'y' => $y - $height / 2],
            ['x' => $x + $width / 2, 'y' => $y - $height / 2],
            ['x' => $x + $width / 2, 'y' => $y + $height / 2],
            ['x' => $x - $width / 2, 'y' => $y + $height / 2],
        ];
        return [[$corners[0], $corners[1]], [$corners[1], $corners[2]], [$corners[2], $corners[3]], [$corners[3], $corners[0]]];
    }

    private function compositeRoomSegments(array $parts): array
    {
        $parts = array_values(array_filter($parts, 'is_array'));
        if (!$parts) return [];
        $axisAlignedRects = array_reduce($parts, static function (bool $valid, array $part): bool {
            return $valid
                && ($part['shape'] ?? 'rect') === 'rect'
                && abs(fmod((float) ($part['rotation'] ?? 0), 360.0)) < 0.0001
                && abs(abs((float) ($part['scaleX'] ?? 1)) - 1) < 0.0001
                && abs(abs((float) ($part['scaleY'] ?? 1)) - 1) < 0.0001;
        }, true);
        if (!$axisAlignedRects) {
            $segments = [];
            foreach ($parts as $part) {
                $part['type'] = 'room';
                unset($part['booleanParts']);
                $segments = array_merge($segments, $this->roomSegments($part));
            }
            return $segments;
        }
        $xs = [];
        $ys = [];
        foreach ($parts as $part) {
            $halfWidth = max(0.5, (float) ($part['width'] ?? 1) / 2);
            $halfHeight = max(0.5, (float) ($part['height'] ?? 1) / 2);
            $xs[] = (float) ($part['x'] ?? 0) - $halfWidth;
            $xs[] = (float) ($part['x'] ?? 0) + $halfWidth;
            $ys[] = (float) ($part['y'] ?? 0) - $halfHeight;
            $ys[] = (float) ($part['y'] ?? 0) + $halfHeight;
        }
        $xs = array_values(array_unique($xs, SORT_REGULAR));
        $ys = array_values(array_unique($ys, SORT_REGULAR));
        sort($xs, SORT_NUMERIC);
        sort($ys, SORT_NUMERIC);
        $occupied = [];
        for ($xIndex = 0; $xIndex < count($xs) - 1; $xIndex++) {
            for ($yIndex = 0; $yIndex < count($ys) - 1; $yIndex++) {
                $x = ($xs[$xIndex] + $xs[$xIndex + 1]) / 2;
                $y = ($ys[$yIndex] + $ys[$yIndex + 1]) / 2;
                $added = false;
                $subtracted = false;
                foreach ($parts as $part) {
                    if (!$this->rectContains($part, $x, $y)) continue;
                    if (($part['operation'] ?? 'add') === 'subtract') $subtracted = true;
                    else $added = true;
                }
                if ($added && !$subtracted) $occupied[$xIndex][$yIndex] = true;
            }
        }
        $segments = [];
        foreach ($occupied as $xIndex => $columns) {
            foreach ($columns as $yIndex => $value) {
                if (!$value) continue;
                $left = $xs[$xIndex];
                $right = $xs[$xIndex + 1];
                $top = $ys[$yIndex];
                $bottom = $ys[$yIndex + 1];
                if (empty($occupied[$xIndex][$yIndex - 1])) {
                    $segments[] = [['x' => $left, 'y' => $top], ['x' => $right, 'y' => $top]];
                }
                if (empty($occupied[$xIndex + 1][$yIndex])) {
                    $segments[] = [['x' => $right, 'y' => $top], ['x' => $right, 'y' => $bottom]];
                }
                if (empty($occupied[$xIndex][$yIndex + 1])) {
                    $segments[] = [['x' => $right, 'y' => $bottom], ['x' => $left, 'y' => $bottom]];
                }
                if (empty($occupied[$xIndex - 1][$yIndex])) {
                    $segments[] = [['x' => $left, 'y' => $bottom], ['x' => $left, 'y' => $top]];
                }
            }
        }
        return $segments;
    }

    private function rectContains(array $part, float $x, float $y): bool
    {
        return abs($x - (float) ($part['x'] ?? 0)) < max(0.5, (float) ($part['width'] ?? 1) / 2)
            && abs($y - (float) ($part['y'] ?? 0)) < max(0.5, (float) ($part['height'] ?? 1) / 2);
    }

    private function transformPoint(array $object, array $point): array
    {
        $centerX = (float) ($object['x'] ?? 0);
        $centerY = (float) ($object['y'] ?? 0);
        $scaleX = (float) ($object['scaleX'] ?? 1);
        $scaleY = (float) ($object['scaleY'] ?? 1);
        if (abs($scaleX) < 0.0001) $scaleX = 1;
        if (abs($scaleY) < 0.0001) $scaleY = 1;
        $dx = ((float) ($point['x'] ?? 0) - $centerX) * $scaleX;
        $dy = ((float) ($point['y'] ?? 0) - $centerY) * $scaleY;
        $angle = deg2rad((float) ($object['rotation'] ?? 0));
        return [
            'x' => $centerX + $dx * cos($angle) - $dy * sin($angle),
            'y' => $centerY + $dx * sin($angle) + $dy * cos($angle),
        ];
    }

    private function subtractOpening(array $segment, array $opening): array
    {
        $start = $segment['start'];
        $end = $segment['end'];
        $dx = (float) $end['x'] - (float) $start['x'];
        $dy = (float) $end['y'] - (float) $start['y'];
        $lengthSquared = $dx * $dx + $dy * $dy;
        if ($lengthSquared < 1) return [];
        $openingDx = (float) $opening['end']['x'] - (float) $opening['start']['x'];
        $openingDy = (float) $opening['end']['y'] - (float) $opening['start']['y'];
        $cross = abs($dx * $openingDy - $dy * $openingDx) / sqrt($lengthSquared);
        if ($cross > 12) return [$segment];
        $centerX = ((float) $opening['start']['x'] + (float) $opening['end']['x']) / 2;
        $centerY = ((float) $opening['start']['y'] + (float) $opening['end']['y']) / 2;
        $tCenter = (($centerX - $start['x']) * $dx + ($centerY - $start['y']) * $dy) / $lengthSquared;
        $closestX = $start['x'] + $tCenter * $dx;
        $closestY = $start['y'] + $tCenter * $dy;
        if ($tCenter <= 0 || $tCenter >= 1 || hypot($centerX - $closestX, $centerY - $closestY) > 35) {
            return [$segment];
        }
        $half = hypot($openingDx, $openingDy) / (2 * sqrt($lengthSquared));
        $beforeT = max(0, $tCenter - $half);
        $afterT = min(1, $tCenter + $half);
        $parts = [];
        if ($beforeT > 0.01) {
            $parts[] = array_merge($segment, [
                'end' => ['x' => $start['x'] + $beforeT * $dx, 'y' => $start['y'] + $beforeT * $dy],
            ]);
        }
        if ($afterT < 0.99) {
            $parts[] = array_merge($segment, [
                'start' => ['x' => $start['x'] + $afterT * $dx, 'y' => $start['y'] + $afterT * $dy],
            ]);
        }
        return $parts;
    }

    private function finishTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new MapBuilderException('map_write_failed', 'Map transaction failed.', 500);
        }
        $this->db->transCommit();
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
