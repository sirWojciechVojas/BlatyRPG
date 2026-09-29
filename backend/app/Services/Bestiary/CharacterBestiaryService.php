<?php

namespace App\Services\Bestiary;

use App\Services\Campaign\CampaignException;
use App\Services\Compendium\CompendiumAccessService;
use App\Services\Compendium\CompendiumSearchNormalizer;
use App\Services\Compendium\CompendiumService;
use App\Services\Compendium\CompendiumSourceHtmlSanitizer;
use CodeIgniter\Database\BaseConnection;

final class CharacterBestiaryService
{
    private $db;
    private $access;
    private $compendium;
    private $sourceSanitizer;

    public function __construct(
        ?BaseConnection $db = null,
        ?CompendiumAccessService $access = null,
        ?CompendiumService $compendium = null,
        ?CompendiumSourceHtmlSanitizer $sourceSanitizer = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->access = $access ?: new CompendiumAccessService($this->db);
        $this->compendium = $compendium ?: new CompendiumService($this->db);
        $this->sourceSanitizer = $sourceSanitizer
            ?: new CompendiumSourceHtmlSanitizer();
    }

    public function index(int $campaignId, int $characterId, array $auth): array
    {
        $context = $this->context($campaignId, $characterId, $auth);
        $rows = $this->catalogRows($context);
        $encountered = [];
        $remaining = [];

        foreach ($rows as $row) {
            $level = $this->normalizeLevel(
                (string) ($row['knowledge_level'] ?? 'unknown')
            );
            $hasFullKnowledge = $level === 'full';
            $item = [
                'id' => (int) $row['id'],
                'level' => $level,
                'encountered' => $hasFullKnowledge,
            ];
            if ($level !== 'unknown') {
                $item['title'] = (string) $row['title'];
                $item['slug'] = (string) $row['slug'];
                $item['excerpt'] = (string) ($row['excerpt'] ?? '');
            }
            if ($hasFullKnowledge) {
                $item['discoveredAt'] = $row['discovered_at'];
                $encountered[] = $item;
            } else {
                $item['locked'] = $level === 'unknown';
                $remaining[] = $item;
            }
        }

        $summaryCount = count(array_filter(
            $remaining,
            static fn (array $item): bool => $item['level'] === 'summary'
        ));

        return [
            'character' => $this->presentCharacter($context['character']),
            'type' => $this->creatureType((int) $context['world']['id']),
            'encountered' => $encountered,
            'remaining' => $remaining,
            'counts' => [
                'encountered' => count($encountered),
                'summary' => $summaryCount,
                'unknown' => count($remaining) - $summaryCount,
                'remaining' => count($remaining),
                'total' => count($rows),
            ],
        ];
    }

    public function show(
        int $campaignId,
        int $characterId,
        int $entryId,
        array $auth
    ): array {
        $context = $this->context($campaignId, $characterId, $auth);
        $catalogRows = $this->catalogRows($context, $entryId);
        if (!$catalogRows) {
            throw new CampaignException(
                'bestiary_entry_not_found',
                'Bestiary entry was not found.',
                404
            );
        }
        if (($catalogRows[0]['knowledge_level'] ?? 'unknown') !== 'full') {
            throw new CampaignException(
                'bestiary_entry_locked',
                'Full information about this creature is not available.',
                403
            );
        }

        $result = $this->compendium->campaignBestiaryShow(
            $campaignId,
            $characterId,
            $entryId,
            $auth
        );
        $entry = $result['entry'];
        foreach (['gmContent', 'gmFields', 'gmNotes', 'statBlocks'] as $field) {
            unset($entry[$field]);
        }
        if (!empty($entry['sourceBacked'])) {
            foreach (
                [
                    'publicContent',
                    'publicFields',
                    'playerDescription',
                    'assets',
                    'mentions',
                    'tags',
                    'categories',
                ] as $field
            ) {
                unset($entry[$field]);
            }
        }
        $entry['capabilities'] = array_merge(
            (array) ($entry['capabilities'] ?? []),
            [
                'canSeeGm' => false,
                'canEdit' => false,
                'canMaterialize' => false,
                'canReveal' => false,
                'canPin' => false,
            ]
        );
        $entry['discoveredAt'] = $catalogRows[0]['discovered_at'] ?? null;

        return [
            'entry' => $entry,
            'character' => $this->presentCharacter($context['character']),
        ];
    }

    public function assignments(
        int $campaignId,
        int $entryId,
        array $auth
    ): array {
        $context = $this->managerContext($campaignId, $auth);
        $this->requiredCreatureEntity($context, $entryId, false);
        $rows = $this->playerCharacters($campaignId);
        $characterIds = array_map('intval', array_column($rows, 'id'));
        $knowledge = [];
        $encounters = [];
        if ($characterIds) {
            foreach ($this->db->table('character_bestiary_knowledge')
                ->select('character_id, knowledge_level')
                ->where('campaign_id', $campaignId)
                ->where('entry_id', $entryId)
                ->whereIn('character_id', $characterIds)
                ->get()
                ->getResultArray() as $row) {
                $knowledge[(int) $row['character_id']] =
                    $this->normalizeLevel((string) $row['knowledge_level']);
            }
            foreach ($this->db->table('character_bestiary_encounters')
                ->select('character_id, discovered_at')
                ->where('campaign_id', $campaignId)
                ->where('entry_id', $entryId)
                ->whereIn('character_id', $characterIds)
                ->get()
                ->getResultArray() as $encounter) {
                $encounters[(int) $encounter['character_id']] =
                    $encounter['discovered_at'];
            }
        }

        return [
            'entryId' => $entryId,
            'sectionKeys' => $this->contentSectionKeys(
                $campaignId,
                $entryId
            ),
            'assignments' => array_map(
                static function (array $row) use ($knowledge, $encounters): array {
                    $characterId = (int) $row['id'];
                    $level = $knowledge[$characterId] ?? 'unknown';
                    $encountered = isset($encounters[$characterId]);
                    return [
                        'characterId' => $characterId,
                        'characterName' => (string) $row['name'],
                        'level' => $level,
                        'state' => $level,
                        'assigned' => $level !== 'unknown',
                        'encountered' => $encountered,
                        'discoveredAt' => $encountered
                            ? $encounters[$characterId]
                            : null,
                    ];
                },
                $rows
            ),
        ];
    }

    public function setAssignment(
        int $campaignId,
        int $entryId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $result = $this->setAssignments(
            $campaignId,
            $entryId,
            $auth,
            [
                'characterIds' => [$characterId],
                'level' => $payload['level'] ?? ($payload['state'] ?? ''),
            ]
        );
        return [
            'entryId' => $entryId,
            'assignment' => $result['assignments'][0],
        ];
    }

    public function setAssignments(
        int $campaignId,
        int $entryId,
        array $auth,
        array $payload
    ): array {
        $requestedLevels = $this->normalizeAssignmentPayload($payload);
        $characterIds = array_keys($requestedLevels);
        $sectionKeysProvided = array_key_exists('sectionKeys', $payload);

        $context = $this->managerContext($campaignId, $auth);
        $entity = $this->requiredCreatureEntity($context, $entryId);
        $sectionKeys = $sectionKeysProvided
            ? $this->validatedSectionKeys($payload['sectionKeys'], $entity)
            : $this->validatedSectionKeys(
                $this->contentSectionKeys($campaignId, $entryId),
                $entity
            );
        $characters = $characterIds
            ? $this->playerCharacters($campaignId, $characterIds)
            : [];
        $charactersById = [];
        foreach ($characters as $character) {
            $charactersById[(int) $character['id']] = $character;
        }
        if (count($charactersById) !== count($characterIds)) {
            throw new CampaignException(
                'character_not_found',
                'A Player Character was not found in this campaign.',
                404
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $userId = (int) $context['auth']['user_id'];
        $unknownIds = [];
        $visibleIds = [];
        foreach ($requestedLevels as $characterId => $level) {
            if ($level === 'unknown') {
                $unknownIds[] = $characterId;
            } else {
                $visibleIds[] = $characterId;
            }
        }

        $this->db->transBegin();
        try {
            $activeRevealIds = [];
            if ($characterIds) {
                $activeRevealIds = array_fill_keys(array_map(
                    'intval',
                    array_column(
                        $this->db->table('compendium_campaign_reveals')
                            ->select('character_id')
                            ->where('campaign_id', $campaignId)
                            ->where('entity_id', (int) $entity['entity_id'])
                            ->where('revoked_at', null)
                            ->whereIn('character_id', $characterIds)
                            ->get()
                            ->getResultArray(),
                        'character_id'
                    )
                ), true);
            }
            if ($unknownIds) {
                $this->db->table('compendium_campaign_reveals')
                    ->where('campaign_id', $campaignId)
                    ->where('entity_id', (int) $entity['entity_id'])
                    ->whereIn('character_id', $unknownIds)
                    ->where('revoked_at', null)
                    ->update(['revoked_at' => $now, 'updated_at' => $now]);
            }
            if ($visibleIds) {
                $missingRevealIds = array_values(array_filter(
                    $visibleIds,
                    static fn (int $id): bool => !isset($activeRevealIds[$id])
                ));
                if ($missingRevealIds) {
                    $selectedHtml = $this->sourceSanitizer->selectSections(
                        (string) $entity['sanitized_html'],
                        $sectionKeys
                    );
                    $snapshot = CompendiumSearchNormalizer::normalize(
                        (string) $entity['name'] . ' '
                        . strip_tags($selectedHtml)
                    );
                    $encodedSectionKeys = json_encode(
                        $sectionKeys,
                        JSON_UNESCAPED_UNICODE
                    );
                    $this->db->table('compendium_campaign_reveals')
                        ->insertBatch(array_map(
                            static function (int $id) use (
                                $campaignId,
                                $encodedSectionKeys,
                                $entity,
                                $now,
                                $snapshot,
                                $userId
                            ): array {
                                return [
                                    'campaign_id' => $campaignId,
                                    'entity_id' => (int) $entity['entity_id'],
                                    'source_revision_id' =>
                                        (int) $entity['source_revision_id'],
                                    'user_id' => null,
                                    'character_id' => $id,
                                    'section_keys_json' => $encodedSectionKeys,
                                    'snapshot_search_text' => $snapshot,
                                    'granted_by_user_id' => $userId,
                                    'revoked_at' => null,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            },
                            $missingRevealIds
                        ));
                }
            }

            if ($sectionKeysProvided) {
                $this->saveContentSectionKeys(
                    $campaignId,
                    $entryId,
                    $sectionKeys,
                    $userId,
                    $now
                );
                $selectedHtml = $this->sourceSanitizer->selectSections(
                    (string) $entity['sanitized_html'],
                    $sectionKeys
                );
                $this->db->table('compendium_campaign_reveals')
                    ->where('campaign_id', $campaignId)
                    ->where('entity_id', (int) $entity['entity_id'])
                    ->where('character_id IS NOT NULL', null, false)
                    ->where('revoked_at', null)
                    ->update([
                        'source_revision_id' =>
                            (int) $entity['source_revision_id'],
                        'section_keys_json' => json_encode(
                            $sectionKeys,
                            JSON_UNESCAPED_UNICODE
                        ),
                        'snapshot_search_text' =>
                            CompendiumSearchNormalizer::normalize(
                                (string) $entity['name'] . ' '
                                . strip_tags($selectedHtml)
                            ),
                        'updated_at' => $now,
                    ]);
            }

            $existingKnowledgeIds = [];
            if ($characterIds) {
                $existingKnowledgeIds = array_fill_keys(array_map(
                    'intval',
                    array_column(
                        $this->db->table('character_bestiary_knowledge')
                            ->select('character_id')
                            ->where('campaign_id', $campaignId)
                            ->where('entry_id', $entryId)
                            ->whereIn('character_id', $characterIds)
                            ->get()
                            ->getResultArray(),
                        'character_id'
                    )
                ), true);
            }
            foreach (['unknown', 'summary', 'full'] as $level) {
                $ids = array_keys(array_filter(
                    $requestedLevels,
                    static fn (string $value): bool => $value === $level
                ));
                $existingIds = array_values(array_filter(
                    $ids,
                    static fn (int $id): bool => isset($existingKnowledgeIds[$id])
                ));
                if ($existingIds) {
                    $this->db->table('character_bestiary_knowledge')
                        ->where('campaign_id', $campaignId)
                        ->where('entry_id', $entryId)
                        ->whereIn('character_id', $existingIds)
                        ->update([
                            'knowledge_level' => $level,
                            'updated_by_user_id' => $userId,
                            'updated_at' => $now,
                        ]);
                }
            }
            $missingKnowledgeIds = array_values(array_filter(
                $characterIds,
                static fn (int $id): bool => !isset($existingKnowledgeIds[$id])
            ));
            if ($missingKnowledgeIds) {
                $this->db->table('character_bestiary_knowledge')
                    ->insertBatch(array_map(
                        static fn (int $id): array => [
                            'campaign_id' => $campaignId,
                            'character_id' => $id,
                            'entry_id' => $entryId,
                            'knowledge_level' => $requestedLevels[$id],
                            'updated_by_user_id' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $missingKnowledgeIds
                    ));
            }

            if ($this->db->transStatus() === false) {
                throw new CampaignException(
                    'bestiary_assignment_failed',
                    'The character bestiary could not be updated.',
                    500
                );
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        $discoveredAt = [];
        if ($characterIds) {
            foreach (
                $this->db->table('character_bestiary_encounters')
                    ->select('character_id, discovered_at')
                    ->where('campaign_id', $campaignId)
                    ->where('entry_id', $entryId)
                    ->whereIn('character_id', $characterIds)
                    ->get()
                    ->getResultArray() as $encounter
            ) {
                $discoveredAt[(int) $encounter['character_id']] =
                    $encounter['discovered_at'];
            }
        }

        return [
            'entryId' => $entryId,
            'sectionKeys' => $sectionKeys,
            'assignments' => array_map(
                static function (int $characterId) use (
                    $charactersById,
                    $discoveredAt,
                    $requestedLevels
                ): array {
                    $level = $requestedLevels[$characterId];
                    $encountered = isset($discoveredAt[$characterId]);
                    return [
                        'characterId' => $characterId,
                        'characterName' =>
                            (string) $charactersById[$characterId]['name'],
                        'level' => $level,
                        'state' => $level,
                        'assigned' => $level !== 'unknown',
                        'encountered' => $encountered,
                        'discoveredAt' => $encountered
                            ? ($discoveredAt[$characterId] ?? null)
                            : null,
                    ];
                },
                $characterIds
            ),
        ];
    }

    private function normalizeAssignmentPayload(array $payload): array
    {
        $rows = $payload['assignments'] ?? null;
        if ($rows === null) {
            $characterIds = $payload['characterIds'] ?? null;
            if (!is_array($characterIds)) {
                if (array_key_exists('sectionKeys', $payload)) {
                    return [];
                }
                $this->invalidAssignmentCount();
            }
            $level = $this->requiredLevel(
                $payload['level'] ?? ($payload['state'] ?? '')
            );
            $rows = array_map(
                static fn ($characterId): array => [
                    'characterId' => $characterId,
                    'level' => $level,
                ],
                $characterIds
            );
        }
        if (!is_array($rows) || count($rows) > 500) {
            $this->invalidAssignmentCount();
        }
        if (!$rows && array_key_exists('sectionKeys', $payload)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new CampaignException(
                    'validation_failed',
                    'Choose valid Player Characters.',
                    422
                );
            }
            $id = filter_var(
                $row['characterId'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($id === false) {
                throw new CampaignException(
                    'validation_failed',
                    'Choose valid Player Characters.',
                    422
                );
            }
            $normalized[(int) $id] = $this->requiredLevel(
                $row['level'] ?? ($row['state'] ?? '')
            );
        }
        if (!$normalized || count($normalized) > 500) {
            $this->invalidAssignmentCount();
        }
        return $normalized;
    }

    private function invalidAssignmentCount(): void
    {
        throw new CampaignException(
            'validation_failed',
            'Choose between 1 and 500 Player Characters.',
            422
        );
    }

    private function requiredLevel($value): string
    {
        $level = (string) $value;
        $level = [
            'hidden' => 'unknown',
            'catalogued' => 'summary',
            'encountered' => 'full',
        ][$level] ?? $level;
        if (!in_array($level, ['unknown', 'summary', 'full'], true)) {
            throw new CampaignException(
                'validation_failed',
                'Choose a valid character bestiary knowledge level.',
                422
            );
        }
        return $level;
    }

    private function normalizeLevel(string $level): string
    {
        $level = [
            'hidden' => 'unknown',
            'catalogued' => 'summary',
            'encountered' => 'full',
        ][$level] ?? $level;
        return in_array($level, ['unknown', 'summary', 'full'], true)
            ? $level
            : 'unknown';
    }

    private function contentSectionKeys(int $campaignId, int $entryId): array
    {
        $row = $this->db->table('campaign_bestiary_entry_content')
            ->select('section_keys_json')
            ->where('campaign_id', $campaignId)
            ->where('entry_id', $entryId)
            ->get()
            ->getRowArray();
        if (!$row) {
            return [];
        }
        $keys = json_decode((string) $row['section_keys_json'], true);
        return is_array($keys)
            ? array_values(array_unique(array_map('strval', $keys)))
            : [];
    }

    private function validatedSectionKeys($value, array $entity): array
    {
        if (!is_array($value) || count($value) > 100) {
            throw new CampaignException(
                'validation_failed',
                'Choose valid player-facing source sections.',
                422
            );
        }
        $selected = array_values(array_unique(array_filter(
            array_map('strval', $value),
            static fn (string $key): bool => $key !== ''
        )));
        $sections = json_decode(
            (string) ($entity['sections_json'] ?? '[]'),
            true
        );
        if (!$sections) {
            $sections = $this->sourceSanitizer->sections(
                (string) ($entity['sanitized_html'] ?? '')
            );
        }
        $allowed = is_array($sections)
            ? array_values(array_map(
                'strval',
                array_column($sections, 'id')
            ))
            : [];
        if (array_diff($selected, $allowed)) {
            throw new CampaignException(
                'validation_failed',
                'A selected source section does not exist.',
                422
            );
        }
        return array_values(array_filter(
            $allowed,
            static fn (string $key): bool => in_array(
                $key,
                $selected,
                true
            )
        ));
    }

    private function saveContentSectionKeys(
        int $campaignId,
        int $entryId,
        array $sectionKeys,
        int $userId,
        string $now
    ): void {
        $builder = $this->db->table('campaign_bestiary_entry_content');
        $existing = $builder
            ->select('id')
            ->where('campaign_id', $campaignId)
            ->where('entry_id', $entryId)
            ->get()
            ->getRowArray();
        $values = [
            'section_keys_json' => json_encode(
                $sectionKeys,
                JSON_UNESCAPED_UNICODE
            ),
            'updated_by_user_id' => $userId,
            'updated_at' => $now,
        ];
        if ($existing) {
            $this->db->table('campaign_bestiary_entry_content')
                ->where('id', (int) $existing['id'])
                ->update($values);
            return;
        }
        $this->db->table('campaign_bestiary_entry_content')->insert(
            $values + [
                'campaign_id' => $campaignId,
                'entry_id' => $entryId,
                'created_at' => $now,
            ]
        );
    }

    private function context(int $campaignId, int $characterId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $character = $this->db->table('characters')
            ->where('id', $characterId)
            ->get()
            ->getRowArray();
        if (!$character || !$this->characterInCampaign($character, $campaignId)) {
            throw new CampaignException(
                'character_not_found',
                'Character was not found in this campaign.',
                404
            );
        }

        $userId = (int) ($auth['user_id'] ?? 0);
        $isManager = !empty($context['campaignContext']['capabilities']['canManage']);
        $isOwner = (int) ($character['user_id'] ?? 0) === $userId
            || $this->db->table('resource_permissions')
                ->where('campaign_id', $campaignId)
                ->where('resource_type', 'character')
                ->where('resource_id', $characterId)
                ->where('user_id', $userId)
                ->where('access_level', 'owner')
                ->countAllResults() > 0;
        if (!$isManager && !$isOwner) {
            throw new CampaignException(
                'forbidden',
                'You cannot read this character bestiary.',
                403
            );
        }

        $context['character'] = $character;
        return $context;
    }

    private function managerContext(int $campaignId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        if (empty($context['campaignContext']['capabilities']['canManage'])) {
            throw new CampaignException(
                'forbidden',
                'Only a campaign manager can assign bestiary knowledge.',
                403
            );
        }
        return $context;
    }

    private function catalogRows(array $context, ?int $entryId = null): array
    {
        $campaignId = (int) $context['campaign']['id'];
        $characterId = (int) $context['character']['id'];

        $builder = $this->db->table('compendium_entries entry')
            ->select(
                'entry.id, entry.slug, version.title, version.excerpt, '
                . 'encounter.id AS encounter_id, encounter.discovered_at, '
                . 'knowledge.knowledge_level'
            )
            ->join(
                'compendium_entry_versions version',
                'version.id = entry.published_version_id',
                'inner'
            )
            ->join(
                'compendium_entry_types entry_type',
                'entry_type.id = version.type_id',
                'inner'
            )
            ->join(
                'character_bestiary_knowledge knowledge',
                'knowledge.entry_id = entry.id '
                . "AND knowledge.campaign_id = {$campaignId} "
                . "AND knowledge.character_id = {$characterId}",
                'left'
            )
            ->join(
                'character_bestiary_encounters encounter',
                'encounter.entry_id = entry.id '
                . "AND encounter.campaign_id = {$campaignId} "
                . "AND encounter.character_id = {$characterId}",
                'left'
            )
            ->where('entry.world_id', (int) $context['world']['id'])
            ->where('entry.status', 'active')
            ->where('entry.deleted_at', null)
            ->where('entry_type.code', 'creature');
        if ($entryId !== null) {
            $builder->where('entry.id', $entryId);
        }
        return $builder->orderBy('version.title', 'ASC')->get()->getResultArray();
    }

    private function creatureType(int $worldId): array
    {
        $row = $this->db->table('compendium_entry_types')
            ->where('world_id', $worldId)
            ->where('code', 'creature')
            ->get()
            ->getRowArray();
        if (!$row) {
            return ['id' => null, 'code' => 'creature', 'name' => 'Bestiary', 'fields' => []];
        }
        $fields = json_decode((string) ($row['field_schema_json'] ?? '[]'), true);
        return [
            'id' => (int) $row['id'],
            'code' => 'creature',
            'name' => (string) $row['name'],
            'fields' => is_array($fields) ? array_values($fields) : [],
        ];
    }

    private function characterInCampaign(array $character, int $campaignId): bool
    {
        if ((int) ($character['campaign_id'] ?? 0) === $campaignId) {
            return true;
        }
        return $this->db->table('character_campaigns')
            ->where('character_id', (int) $character['id'])
            ->where('campaign_id', $campaignId)
            ->countAllResults() > 0;
    }

    private function requiredCreatureEntity(
        array $context,
        int $entryId,
        bool $includeSourceContent = true
    ): array {
        $builder = $this->db->table('compendium_entries entry')
            ->select(
                'entity.id AS entity_id, entity.name, '
                . 'entity.current_source_revision_id AS source_revision_id'
            )
            ->join(
                'compendium_entry_versions version',
                'version.id=entry.published_version_id',
                'inner'
            )
            ->join(
                'compendium_entry_types entry_type',
                'entry_type.id=version.type_id',
                'inner'
            )
            ->join(
                'compendium_entities entity',
                'entity.entry_id=entry.id AND entity.deleted_at IS NULL',
                'inner'
            )
            ->where('entry.id', $entryId)
            ->where('entry.world_id', (int) $context['world']['id'])
            ->where('entry.status', 'active')
            ->where('entry.deleted_at', null)
            ->where('entry_type.code', 'creature');
        if ($includeSourceContent) {
            $builder->select(
                'revision.sanitized_html, revision.sections_json'
            )
                ->join(
                    'compendium_source_revisions revision',
                    'revision.id=entity.current_source_revision_id',
                    'inner'
                );
        }
        $row = $builder->get()->getRowArray();
        if (!$row) {
            throw new CampaignException(
                'bestiary_entry_not_found',
                'Bestiary entry was not found.',
                404
            );
        }
        return $row;
    }

    private function playerCharacters(
        int $campaignId,
        ?array $characterIds = null
    ): array {
        $campaignIdSql = (int) $campaignId;
        $playerUserIds = $this->activePlayerUserIds($campaignId);
        $playerOwnedCharacterIds = $this->playerOwnedCharacterIds(
            $campaignId,
            $playerUserIds
        );
        $builder = $this->db->table('characters character_row')
            ->distinct()
            ->select(
                'character_row.id, character_row.name, '
                . 'character_row.user_id, character_row.data'
            )
            ->join(
                'character_campaigns campaign_assignment',
                'campaign_assignment.character_id=character_row.id '
                . "AND campaign_assignment.campaign_id={$campaignIdSql}",
                'left'
            )
            ->groupStart()
            ->where('character_row.campaign_id', $campaignId)
            ->orWhere('campaign_assignment.campaign_id', $campaignId)
            ->groupEnd();
        if ($characterIds !== null) {
            $builder->whereIn('character_row.id', $characterIds);
        }
        $rows = $builder
            ->orderBy('character_row.name', 'ASC')
            ->get()
            ->getResultArray();
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->isPlayerCharacter(
                $row,
                $playerUserIds,
                $playerOwnedCharacterIds
            )
        ));
    }

    private function activePlayerUserIds(int $campaignId): array
    {
        return array_values(array_unique(array_map(
            'intval',
            array_column(
                $this->db->table('campaign_members')
                    ->select('user_id')
                    ->where('campaign_id', $campaignId)
                    ->where('role', 'player')
                    ->where('is_active', 1)
                    ->get()
                    ->getResultArray(),
                'user_id'
            )
        )));
    }

    private function playerOwnedCharacterIds(
        int $campaignId,
        array $playerUserIds
    ): array {
        if (!$playerUserIds) {
            return [];
        }
        return array_fill_keys(array_map(
            'intval',
            array_column(
                $this->db->table('resource_permissions')
                    ->select('resource_id')
                    ->where('campaign_id', $campaignId)
                    ->where('resource_type', 'character')
                    ->where('access_level', 'owner')
                    ->whereIn('user_id', $playerUserIds)
                    ->get()
                    ->getResultArray(),
                'resource_id'
            )
        ), true);
    }

    private function isPlayerCharacter(
        array $character,
        array $playerUserIds,
        array $playerOwnedCharacterIds
    ): bool {
        $characterId = (int) ($character['id'] ?? 0);
        $ownerUserId = (int) ($character['user_id'] ?? 0);
        if (
            in_array($ownerUserId, $playerUserIds, true)
            || isset($playerOwnedCharacterIds[$characterId])
        ) {
            return true;
        }

        $data = $character['data'] ?? [];
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }
        $gamerName = strtolower(trim((string) (
            $data['meta']['gamer_name'] ?? ''
        )));
        return $gamerName !== ''
            && !in_array(
                $gamerName,
                ['gm', 'mg', 'game master', 'mistrz gry'],
                true
            );
    }

    private function presentCharacter(array $character): array
    {
        return [
            'id' => (int) $character['id'],
            'name' => (string) $character['name'],
        ];
    }
}
