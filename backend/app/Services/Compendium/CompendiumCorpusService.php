<?php

namespace App\Services\Compendium;

use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseConnection;

/** Read-side and campaign state for source-backed Compendium entities. */
final class CompendiumCorpusService
{
    private const DEPARTMENT_TYPES = [
        'world_atlas' => ['polity', 'location', 'lore', 'language', 'law', 'economy'],
        'history' => ['event', 'calendar'], 'characters' => ['character', 'person'],
        'bestiary' => ['creature', 'species'], 'factions' => ['faction'],
        'religion' => ['deity'], 'magic' => ['spell', 'magic_tradition'],
        'mechanics' => ['career', 'skill', 'talent'], 'equipment' => ['item', 'weapon'],
        'gm_tools' => ['rolltable'],
    ];
    private const DEPARTMENT_KEYWORDS = [
        'history' => ['historia', 'wielkie bitwy', 'czasy konca', 'kampanie wojenne'],
        'characters' => ['bohaterowie', 'postacie'],
        'bestiary' => ['bestie', 'zwierzeta', 'stworzenia', 'potwory', 'demony', 'smoki'],
        'factions' => ['frakcje', 'organizacje', 'zakony', 'jednostki'],
        'religion' => ['bogowie', 'religie', 'wierzenia', 'kult ', 'kosciol'],
        'chaos' => ['chaos', 'tzeentch', 'slaanesh', 'khorne', 'nurgle', 'demony'],
        'magic' => ['magia', 'wiatry magii', 'kolegia magii', 'czarodzieje', 'zaklecia'],
        'mechanics' => ['profesje', 'umiejetnosci', 'zdolnosci', 'mechanika'],
        'equipment' => ['zbrojownia', 'zbrojownie', 'ekwipunek', 'bronie', 'pancerze', 'amulety'],
        'gm_tools' => ['narzedzia mg', 'tabele losowe'], 'maps' => ['mapy', 'mapa'],
        'sources' => ['zrodla', 'ksiegi', 'podreczniki'],
    ];
    private $db;
    private $sanitizer;

    public function __construct(?BaseConnection $db = null, ?CompendiumSourceHtmlSanitizer $sanitizer = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->sanitizer = $sanitizer ?: new CompendiumSourceHtmlSanitizer();
    }

    public function entityForEntry(array $context, int $entryId): ?array
    {
        return $this->db->table('compendium_entities ce')
            ->select('ce.id, ce.world_id, ce.entry_id, ce.source_document_id, ce.current_source_revision_id, '
                . 'ce.canonical_id, ce.type_code, ce.slug, ce.name, ce.normalized_name, ce.aliases_normalized, '
                . 'ce.player_description, ce.gm_notes, ce.visibility, ce.spoiler_level, ce.editorial_status, '
                . 'ce.verification_status, ce.canon_status, ce.edition, '
                . 'd.external_id, d.source_uri, s.source_key, s.name AS source_name, '
                . 's.language AS source_language, s.edition AS source_edition, s.license_status, s.attribution_status, '
                . 'sr.id AS source_revision_id, sr.external_revision_id, sr.checksum, sr.source_timestamp, sr.contributor, '
                . 'sr.sanitized_html, sr.sections_json, '
                . 'sr.quality_flags_json, sr.source_payload_json')
            ->join('compendium_source_documents d', 'd.id=ce.source_document_id', 'inner')
            ->join('compendium_sources s', 's.id=d.source_id', 'inner')
            ->join('compendium_source_revisions sr', 'sr.id=ce.current_source_revision_id', 'left')
            ->where('ce.world_id', (int) $context['world']['id'])->where('ce.entry_id', $entryId)
            ->where('ce.deleted_at', null)->get()->getRowArray() ?: null;
    }

    public function canRead(array $context, array $entity): bool
    {
        if (!empty($context['canSeeGm'])) {
            return true;
        }
        if (in_array($entity['visibility'] ?? '', ['public', 'player'], true)
            && ($entity['verification_status'] ?? '') === 'verified') {
            return true;
        }
        return $this->activeReveal($context, (int) $entity['id']) !== null;
    }

    public function decorateList(array $context, array $result, ?array $entity = null): array
    {
        $entity = $entity ?: $this->entityForEntry($context, (int) $result['id']);
        if (!$entity) {
            $result['sourceBacked'] = false;
            return $result;
        }
        $result['sourceBacked'] = true;
        $result['canonicalId'] = (string) $entity['canonical_id'];
        $result['sourceId'] = (string) $entity['external_id'];
        $result['sourceKey'] = (string) $entity['source_key'];
        $result['sourceName'] = (string) $entity['source_name'];
        $result['sourceType'] = (string) $entity['type_code'];
        $result['visibility'] = (string) $entity['visibility'];
        $result['spoilerLevel'] = (string) $entity['spoiler_level'];
        $result['verificationStatus'] = (string) $entity['verification_status'];
        $result['editorialStatus'] = (string) $entity['editorial_status'];
        $result['canonStatus'] = (string) $entity['canon_status'];
        $result['edition'] = $entity['edition'];
        $result['categories'] = $this->categories((int) $entity['id']);
        $categoryNames = array_column($result['categories'], 'name');
        $result['departments'] = $this->departments($entity, $categoryNames);
        $result['department'] = $result['departments'][0] ?? 'world_atlas';
        $sourcePayload = $this->json($entity['source_payload_json'] ?? null);
        $result['npcKind'] = $this->npcKind(
            $result['departments'],
            $sourcePayload['npcKind'] ?? null
        );
        $activity = $this->activityRow($context, (int) $entity['id']);
        $result['favorite'] = !empty($activity['is_favorite']);
        $result['lastReadAt'] = $activity['last_read_at'] ?? null;
        return $result;
    }

    /** Decorates a paged list row without issuing per-entry queries. */
    public function decorateListRow(array $result, array $row): array
    {
        if (empty($row['corpus_entity_id'])) return $result + ['sourceBacked' => false];
        $entity = [
            'type_code' => $row['corpus_type_code'], 'name' => $row['title'],
            'aliases_normalized' => $row['corpus_aliases_normalized'] ?? null,
        ];
        $categoryNames = (string) ($row['corpus_category_names'] ?? '');
        $departments = $this->departments($entity, preg_split('/\|/u', $categoryNames, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $npcKind = strpos($categoryNames, 'generyczni') !== false ? 'generic' : null;
        return array_merge($result, [
            'sourceBacked' => true,
            'canonicalId' => $row['corpus_canonical_id'] ?? null,
            'sourceId' => $row['corpus_source_id'] ?? null,
            'sourceKey' => $row['corpus_source_key'] ?? null,
            'sourceName' => $row['corpus_source_name'] ?? null,
            'sourceType' => $row['corpus_type_code'],
            'department' => $departments[0] ?? 'world_atlas', 'departments' => $departments,
            'npcKind' => $this->npcKind($departments, $npcKind),
            'visibility' => $row['corpus_visibility'],
            'spoilerLevel' => $row['corpus_spoiler_level'],
            'verificationStatus' => $row['corpus_verification_status'],
            'editorialStatus' => $row['corpus_editorial_status'],
            'canonStatus' => $row['corpus_canon_status'],
            'edition' => $row['corpus_edition'],
            'categories' => [], 'favorite' => false, 'lastReadAt' => null,
        ]);
    }

    public function decorateDetail(array $context, array $result, array $entity): array
    {
        $result = $this->decorateList($context, $result, $entity);
        $revision = $this->allowedRevision($context, $entity);
        if (!$revision) {
            throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        }
        $reveal = $revision['_reveal'] ?? null;
        unset($revision['_reveal']);
        $html = (string) $revision['sanitized_html'];
        $sectionKeys = $reveal ? $this->json($reveal['section_keys_json']) : [];
        if ($reveal && $reveal['section_keys_json'] !== null) {
            $html = $this->sanitizer->selectSections($html, $sectionKeys);
        }
        $result['sourceHtml'] = $html;
        $result['playerDescription'] = $entity['player_description'];
        $availableSections = $this->json($revision['sections_json']);
        if (!$availableSections) {
            $availableSections = $this->sanitizer->sections(
                (string) $revision['sanitized_html']
            );
        }
        $result['sections'] = array_values(array_filter(
            $availableSections,
            static function (array $section) use ($sectionKeys, $reveal): bool {
                return !$reveal || $reveal['section_keys_json'] === null || in_array((string) ($section['id'] ?? ''), $sectionKeys, true);
            }
        ));
        $result['source'] = [
            'key' => (string) $entity['source_key'], 'name' => (string) $entity['source_name'],
            'documentId' => (string) $entity['external_id'], 'url' => $entity['source_uri'],
            'revisionId' => (string) $revision['external_revision_id'],
            'revisionTimestamp' => $revision['source_timestamp'],
            'contributor' => $revision['contributor'], 'checksum' => (string) $revision['checksum'],
            'language' => (string) $entity['source_language'], 'edition' => $entity['source_edition'],
            'licenseStatus' => (string) $entity['license_status'],
            'attributionStatus' => (string) $entity['attribution_status'],
            'immutableReveal' => (bool) $reveal,
        ];
        $result['qualityFlags'] = $this->json($revision['quality_flags_json']);
        // A section reveal is an immutable, deliberately narrow snapshot. Link and
        // asset registries describe the whole source revision, so exposing them
        // here could disclose names or filenames from sections the GM withheld.
        $partialReveal = $reveal && $reveal['section_keys_json'] !== null;
        $wikiLinks = $this->wikiLinks($context, (int) $entity['id'], (int) $revision['id']);
        if ($partialReveal) {
            $visibleSourceIds = $this->sourceIdsInHtml($html);
            $wikiLinks = array_values(array_filter($wikiLinks, static function (array $link) use ($visibleSourceIds): bool {
                return isset($visibleSourceIds[(string) ($link['sourceId'] ?? '')]);
            }));
        }
        $result['wikiLinks'] = $wikiLinks;
        $result['wikiBacklinks'] = $partialReveal ? [] : $this->wikiBacklinks($context, (int) $entity['id']);
        $result['semanticRelations'] = $partialReveal ? [] : $this->semanticRelations($context, (int) $entity['id']);
        $result['corpusAssets'] = $partialReveal ? [] : $this->assets($context, (int) $entity['id'], (int) $revision['id']);
        $result['mechanicalProfiles'] = $this->mechanics($context, (int) $entity['id']);
        if (!empty($context['canSeeGm'])) {
            $result['gmNotes'] = $entity['gm_notes'];
            $result['campaignNotes'] = $this->campaignNotes($context, (int) $entity['id']);
            $result['reveal'] = $this->revealPresentation($context, (int) $entity['id']);
        } else {
            $result['campaignNotes'] = $this->campaignNotes($context, (int) $entity['id'], true);
            if ($partialReveal) $result['categories'] = [];
        }
        return $result;
    }

    public function overview(array $context): array
    {
        $worldId = (int) $context['world']['id'];
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        $userId = (int) ($context['auth']['user_id'] ?? 0);
        $access = $this->accessSql($context, 'ce');
        $categories = $this->db->query(
            'SELECT c.id,c.name,c.normalized_name,COUNT(DISTINCT ce.id) AS record_count '
            . 'FROM compendium_source_categories c '
            . 'JOIN compendium_entity_categories ec ON ec.category_id=c.id '
            . 'JOIN compendium_entities ce ON ce.id=ec.entity_id '
            . "WHERE ce.world_id=? AND ce.deleted_at IS NULL AND ({$access['sql']}) "
            . 'GROUP BY c.id,c.name,c.normalized_name ORDER BY record_count DESC,c.name ASC LIMIT 500',
            array_merge([$worldId], $access['bindings'])
        )->getResultArray();
        $sources = $this->db->query(
            'SELECT s.id,s.source_key,s.name,s.language,s.edition,s.license_status,s.attribution_status,COUNT(DISTINCT ce.id) AS record_count '
            . 'FROM compendium_sources s JOIN compendium_source_documents d ON d.source_id=s.id '
            . 'JOIN compendium_entities ce ON ce.source_document_id=d.id '
            . "WHERE ce.world_id=? AND ce.deleted_at IS NULL AND ({$access['sql']}) GROUP BY s.id",
            array_merge([$worldId], $access['bindings'])
        )->getResultArray();
        $departmentCounts = $this->departmentCounts(
            $worldId,
            $access['sql'],
            $access['bindings']
        );
        $departments = [];
        foreach ($this->departmentDefinitions() as $key => $label) {
            $departments[$key] = [
                'key' => $key,
                'label' => $label,
                'count' => (int) ($departmentCounts[$key] ?? 0),
            ];
        }
        $favorites = 0;
        $recent = 0;
        if ($campaignId && $userId) {
            $favorites = $this->db->table('compendium_user_activity')->where('campaign_id', $campaignId)
                ->where('user_id', $userId)->where('is_favorite', 1)->countAllResults();
            $recent = $this->db->table('compendium_user_activity')->where('campaign_id', $campaignId)
                ->where('user_id', $userId)->where('last_read_at IS NOT NULL', null, false)->countAllResults();
        }
        $genericNpcs = (int) ($departmentCounts['_generic_npcs'] ?? 0);
        $allNpcs = (int) ($departmentCounts['characters'] ?? 0);
        return [
            'system' => ['id' => $this->campaignSystemId($context), 'code' => 'wfrp2ed', 'name' => 'Warhammer Fantasy Roleplay 2e'],
            'corpus' => ['count' => (int) ($departmentCounts['_total'] ?? 0), 'categories' => count($categories), 'sources' => count($sources)],
            'categories' => array_map(static function (array $row): array {
                return ['id' => (int) $row['id'], 'name' => $row['name'], 'count' => (int) $row['record_count']];
            }, $categories),
            'sources' => array_map(static function (array $row): array {
                return ['id' => (int) $row['id'], 'key' => $row['source_key'], 'name' => $row['name'],
                    'language' => $row['language'], 'edition' => $row['edition'], 'count' => (int) $row['record_count'],
                    'licenseStatus' => $row['license_status'], 'attributionStatus' => $row['attribution_status']];
            }, $sources),
            'departments' => array_values($departments),
            'npcs' => [
                'all' => $allNpcs,
                'named' => max(0, $allNpcs - $genericNpcs),
                'generic' => $genericNpcs,
            ],
            'activity' => ['favorites' => $favorites, 'recent' => $recent],
        ];
    }

    /** Returns an EXISTS fragment and bindings for list/overview ACL queries. */
    public function accessSql(array $context, string $alias = 'ce'): array
    {
        if (!empty($context['canSeeGm'])) {
            return ['sql' => '1=1', 'bindings' => []];
        }
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        $userId = (int) ($context['auth']['user_id'] ?? 0);
        return [
            'sql' => "(({$alias}.visibility IN ('public','player') AND {$alias}.verification_status='verified') OR EXISTS ("
                . 'SELECT 1 FROM compendium_campaign_reveals cr WHERE cr.entity_id=' . $alias . '.id '
                . 'AND cr.campaign_id=? AND cr.character_id IS NULL '
                . 'AND cr.revoked_at IS NULL AND (cr.user_id IS NULL OR cr.user_id=?)))',
            'bindings' => [$campaignId, $userId],
        ];
    }

    public function favorite(array $context, int $entryId, bool $favorite): array
    {
        $entity = $this->requiredReadableEntity($context, $entryId);
        $campaignId = $this->requiredCampaignId($context);
        $userId = (int) $context['auth']['user_id'];
        $row = $this->activityRow($context, (int) $entity['id']);
        $now = $this->now();
        if ($row) {
            $this->db->table('compendium_user_activity')->where('id', (int) $row['id'])
                ->update(['is_favorite' => $favorite ? 1 : 0, 'updated_at' => $now]);
        } else {
            $this->db->table('compendium_user_activity')->insert([
                'user_id' => $userId, 'campaign_id' => $campaignId, 'entity_id' => (int) $entity['id'],
                'is_favorite' => $favorite ? 1 : 0, 'last_read_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        return ['entryId' => $entryId, 'favorite' => $favorite];
    }

    /** Records optional telemetry outside the latency-sensitive article GET. */
    public function recordRead(array $context, int $entryId): array
    {
        $campaignId = $this->requiredCampaignId($context);
        $userId = (int) ($context['auth']['user_id'] ?? 0);
        $entity = $this->db->table('compendium_entities')
            ->select('id,visibility,verification_status')
            ->where('world_id', (int) $context['world']['id'])
            ->where('entry_id', $entryId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
        if (!$entity || !$this->canRead($context, $entity)) {
            throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        }

        $now = $this->now();
        $this->db->query(
            'INSERT INTO compendium_user_activity '
            . '(user_id,campaign_id,entity_id,is_favorite,last_read_at,created_at,updated_at) '
            . 'VALUES (?,?,?,0,?,?,?) ON DUPLICATE KEY UPDATE '
            . 'last_read_at=VALUES(last_read_at),updated_at=VALUES(updated_at)',
            [$userId, $campaignId, (int) $entity['id'], $now, $now, $now]
        );
        return ['entryId' => $entryId, 'recorded' => true];
    }

    public function reveal(array $context, int $entryId, array $payload): array
    {
        $this->requireCampaignManager($context);
        $entity = $this->entityForEntry($context, $entryId);
        if (!$entity || empty($entity['current_source_revision_id'])) {
            throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        }
        $sectionKeys = array_key_exists('sectionKeys', $payload) ? $payload['sectionKeys'] : null;
        if ($sectionKeys !== null) {
            if (!is_array($sectionKeys) || !$sectionKeys || count($sectionKeys) > 100) {
                throw new CampaignException('validation_failed', 'Select one or more valid sections.', 422);
            }
            $allowed = array_column($this->json($entity['sections_json']), 'id');
            $sectionKeys = array_values(array_unique(array_map('strval', $sectionKeys)));
            if (array_diff($sectionKeys, $allowed)) {
                throw new CampaignException('validation_failed', 'A selected section does not exist.', 422);
            }
        }
        $campaignId = $this->requiredCampaignId($context);
        $targetUserId = isset($payload['userId']) ? (int) $payload['userId'] : null;
        if ($targetUserId && !$this->db->table('campaign_members')->where('campaign_id', $campaignId)
            ->where('user_id', $targetUserId)->where('is_active', 1)->where('left_at', null)->countAllResults()) {
            throw new CampaignException('validation_failed', 'Reveal target is not an active campaign member.', 422);
        }
        $html = $sectionKeys === null ? (string) $entity['sanitized_html']
            : $this->sanitizer->selectSections((string) $entity['sanitized_html'], $sectionKeys);
        $this->db->table('compendium_campaign_reveals')->where('campaign_id', $campaignId)
            ->where('entity_id', (int) $entity['id'])->where('user_id', $targetUserId)
            ->where('character_id', null)
            ->where('revoked_at', null)->update(['revoked_at' => $this->now(), 'updated_at' => $this->now()]);
        $now = $this->now();
        $this->db->table('compendium_campaign_reveals')->insert([
            'campaign_id' => $campaignId, 'entity_id' => (int) $entity['id'],
            'source_revision_id' => (int) $entity['current_source_revision_id'], 'user_id' => $targetUserId,
            'character_id' => null,
            'section_keys_json' => $sectionKeys === null ? null : json_encode($sectionKeys),
            'snapshot_search_text' => CompendiumSearchNormalizer::normalize($entity['name'] . ' ' . strip_tags($html)),
            'granted_by_user_id' => (int) $context['auth']['user_id'], 'revoked_at' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return ['entryId' => $entryId, 'reveal' => $this->revealPresentation($context, (int) $entity['id'])];
    }

    public function revokeReveal(array $context, int $entryId): array
    {
        $this->requireCampaignManager($context);
        $entity = $this->entityForEntry($context, $entryId);
        if (!$entity) throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        $this->db->table('compendium_campaign_reveals')->where('campaign_id', $this->requiredCampaignId($context))
            ->where('entity_id', (int) $entity['id'])->where('character_id', null)
            ->where('revoked_at', null)
            ->update(['revoked_at' => $this->now(), 'updated_at' => $this->now()]);
        return ['entryId' => $entryId, 'revoked' => true];
    }

    public function pin(array $context, int $entryId): array
    {
        $this->requireCampaignManager($context);
        $entity = $this->entityForEntry($context, $entryId);
        if (!$entity) throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        $campaignId = $this->requiredCampaignId($context);
        $existing = $this->db->table('compendium_campaign_instances')->where('campaign_id', $campaignId)
            ->where('entity_id', (int) $entity['id'])->where('kind', 'journal_pin')->where('deleted_at', null)->get()->getRowArray();
        if ($existing) return ['instanceId' => (int) $existing['id'], 'entryId' => $entryId, 'created' => false];
        $now = $this->now();
        $this->db->table('compendium_campaign_instances')->insert([
            'campaign_id' => $campaignId, 'entity_id' => (int) $entity['id'],
            'source_revision_id' => (int) $entity['current_source_revision_id'], 'kind' => 'journal_pin',
            'target_type' => null, 'target_id' => null, 'state_json' => '{}',
            'created_by_user_id' => (int) $context['auth']['user_id'],
            'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        return ['instanceId' => (int) $this->db->insertID(), 'entryId' => $entryId, 'created' => true];
    }

    public function saveNote(array $context, int $entryId, array $payload): array
    {
        $this->requireCampaignManager($context);
        $entity = $this->entityForEntry($context, $entryId);
        if (!$entity) throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        $body = trim((string) ($payload['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 50000) throw new CampaignException('validation_failed', 'Note body is invalid.', 422);
        $visibility = (string) ($payload['visibility'] ?? 'gm');
        if (!in_array($visibility, ['player', 'gm'], true)) throw new CampaignException('validation_failed', 'Note visibility is invalid.', 422);
        $now = $this->now();
        $this->db->table('compendium_campaign_notes')->insert([
            'campaign_id' => $this->requiredCampaignId($context), 'entity_id' => (int) $entity['id'],
            'author_user_id' => (int) $context['auth']['user_id'], 'visibility' => $visibility,
            'body' => $body, 'revision' => 1, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
        ]);
        return ['note' => ['id' => (int) $this->db->insertID(), 'body' => $body, 'visibility' => $visibility]];
    }

    private function requiredReadableEntity(array $context, int $entryId): array
    {
        $entity = $this->entityForEntry($context, $entryId);
        if (!$entity || !$this->canRead($context, $entity)) {
            throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        }
        return $entity;
    }

    private function allowedRevision(array $context, array $entity): ?array
    {
        // A character bestiary is deliberately narrower than the campaign
        // Compendium. Even a public, verified corpus entry must use the
        // character-scoped reveal so that unselected source sections cannot
        // leak into the HUD reader.
        if ((int) ($context['characterBestiaryCharacterId'] ?? 0) > 0) {
            $reveal = $this->activeReveal($context, (int) $entity['id']);
            if (!$reveal) return null;
            $row = $this->db->table('compendium_source_revisions')
                ->where('id', (int) $reveal['source_revision_id'])
                ->get()
                ->getRowArray();
            if ($row) $row['_reveal'] = $reveal;
            return $row ?: null;
        }
        if (!empty($context['canSeeGm']) || (in_array($entity['visibility'], ['public', 'player'], true)
            && $entity['verification_status'] === 'verified')) {
            if (empty($entity['current_source_revision_id']) || empty($entity['external_revision_id'])) {
                return null;
            }
            // entityForEntry already joined the current revision. Reusing it avoids
            // reading the article's LONGTEXT payload for a second time.
            return [
                'id' => (int) $entity['current_source_revision_id'],
                'external_revision_id' => $entity['external_revision_id'],
                'checksum' => $entity['checksum'],
                'source_timestamp' => $entity['source_timestamp'],
                'contributor' => $entity['contributor'],
                'sanitized_html' => $entity['sanitized_html'],
                'sections_json' => $entity['sections_json'],
                'quality_flags_json' => $entity['quality_flags_json'],
            ];
        }
        $reveal = $this->activeReveal($context, (int) $entity['id']);
        if (!$reveal) return null;
        $row = $this->db->table('compendium_source_revisions')->where('id', (int) $reveal['source_revision_id'])->get()->getRowArray();
        if ($row) $row['_reveal'] = $reveal;
        return $row ?: null;
    }

    private function activeReveal(array $context, int $entityId): ?array
    {
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        $userId = (int) ($context['auth']['user_id'] ?? 0);
        if (!$campaignId || !$userId) return null;
        $builder = $this->db->table('compendium_campaign_reveals')
            ->where('campaign_id', $campaignId)
            ->where('entity_id', $entityId)
            ->where('revoked_at', null);
        $characterId = (int) ($context['characterBestiaryCharacterId'] ?? 0);
        if ($characterId > 0) {
            $builder->where('character_id', $characterId);
        } else {
            $builder->where('character_id', null)
                ->groupStart()
                ->where('user_id', null)
                ->orWhere('user_id', $userId)
                ->groupEnd()
                ->orderBy('user_id IS NOT NULL', 'DESC', false);
        }
        return $builder->orderBy('created_at', 'DESC')
            ->get()->getRowArray() ?: null;
    }

    private function categories(int $entityId): array
    {
        return array_map(static function (array $row): array {
            return ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        }, $this->db->table('compendium_entity_categories ec')->select('c.id,c.name')
            ->join('compendium_source_categories c', 'c.id=ec.category_id', 'inner')
            ->where('ec.entity_id', $entityId)->orderBy('c.name')->get()->getResultArray());
    }

    private function wikiLinks(array $context, int $entityId, int $revisionId): array
    {
        $rows = $this->db->table('compendium_wiki_links l')
            ->select('l.id,l.target_external_id,l.anchor,target.id AS readable_entity_id,'
                . 'target.entry_id,target.name,target.visibility,target.verification_status')
            ->join('compendium_entities target', 'target.id=l.target_entity_id', 'inner')
            ->where('l.from_entity_id', $entityId)
            ->where('l.source_revision_id', $revisionId)
            ->where('target.deleted_at', null)
            ->orderBy('l.id')->get()->getResultArray();
        $targets = [];
        foreach ($rows as $row) {
            $targets[(int) $row['readable_entity_id']] = [
                'visibility' => $row['visibility'],
                'verification_status' => $row['verification_status'],
            ];
        }
        $readable = $this->readableEntityIds($context, $targets);
        $result = [];
        foreach ($rows as $row) {
            if (!isset($readable[(int) $row['readable_entity_id']])) continue;
            $result[] = ['targetEntryId' => (int) $row['entry_id'], 'sourceId' => $row['target_external_id'],
                'title' => $row['name'], 'anchor' => $row['anchor'], 'kind' => 'document_link'];
        }
        return $result;
    }

    private function wikiBacklinks(array $context, int $entityId): array
    {
        $rows = $this->db->table('compendium_wiki_links l')
            ->select('source.id AS source_entity_id,source.entry_id,source.name,'
                . 'source.visibility,source.verification_status,l.target_title')
            ->join('compendium_entities source', 'source.id=l.from_entity_id', 'inner')
            ->where('l.target_entity_id', $entityId)->where('l.status', 'resolved')
            ->where('source.deleted_at', null)
            ->where('source.current_source_revision_id=l.source_revision_id', null, false)->limit(100)->get()->getResultArray();
        $sources = [];
        foreach ($rows as $row) {
            $sources[(int) $row['source_entity_id']] = [
                'visibility' => $row['visibility'],
                'verification_status' => $row['verification_status'],
            ];
        }
        $readable = $this->readableEntityIds($context, $sources);
        $result = [];
        foreach ($rows as $row) {
            if (!isset($readable[(int) $row['source_entity_id']])) continue;
            $result[] = ['sourceEntryId' => (int) $row['entry_id'], 'title' => $row['name'], 'kind' => 'document_link'];
        }
        return $result;
    }

    private function semanticRelations(array $context, int $entityId): array
    {
        $rows = $this->db->query(
            'SELECT r.*,s.entry_id AS subject_entry_id,s.name AS subject_name,'
            . 's.visibility AS subject_visibility,s.verification_status AS subject_verification_status,'
            . 'o.entry_id AS object_entry_id,o.name AS object_name,'
            . 'o.visibility AS object_visibility,o.verification_status AS object_verification_status '
            . 'FROM compendium_semantic_relations r JOIN compendium_entities s ON s.id=r.subject_entity_id '
            . 'JOIN compendium_entities o ON o.id=r.object_entity_id WHERE r.subject_entity_id=? OR r.object_entity_id=?',
            [$entityId, $entityId]
        )->getResultArray();
        $targets = [];
        foreach ($rows as $row) {
            $inverse = (int) $row['object_entity_id'] === $entityId;
            $targets[(int) ($inverse ? $row['subject_entity_id'] : $row['object_entity_id'])] = [
                'visibility' => $inverse ? $row['subject_visibility'] : $row['object_visibility'],
                'verification_status' => $inverse
                    ? $row['subject_verification_status']
                    : $row['object_verification_status'],
            ];
        }
        $readable = $this->readableEntityIds($context, $targets);
        $result = [];
        foreach ($rows as $row) {
            if (empty($context['canSeeGm']) && $row['visibility'] !== 'player' && $row['visibility'] !== 'public') continue;
            $inverse = (int) $row['object_entity_id'] === $entityId;
            $targetEntityId = (int) ($inverse ? $row['subject_entity_id'] : $row['object_entity_id']);
            if (!isset($readable[$targetEntityId])) continue;
            $result[] = ['predicate' => $row['predicate'], 'inverse' => $inverse,
                'targetEntryId' => (int) ($inverse ? $row['subject_entry_id'] : $row['object_entry_id']),
                'title' => $inverse ? $row['subject_name'] : $row['object_name'], 'status' => $row['status'],
                'edition' => $row['edition'], 'validTime' => $this->json($row['valid_time_json'])];
        }
        return $result;
    }

    /** Resolves corpus ACLs for a group without an access query per link. */
    private function readableEntityIds(array $context, array $entities): array
    {
        if (!$entities) return [];
        if (!empty($context['canSeeGm'])) return array_fill_keys(array_keys($entities), true);

        $readable = [];
        $pending = [];
        foreach ($entities as $entityId => $entity) {
            if (in_array($entity['visibility'] ?? '', ['public', 'player'], true)
                && ($entity['verification_status'] ?? '') === 'verified') {
                $readable[(int) $entityId] = true;
            } else {
                $pending[] = (int) $entityId;
            }
        }
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        $userId = (int) ($context['auth']['user_id'] ?? 0);
        if (!$pending || !$campaignId || !$userId) return $readable;

        $builder = $this->db->table('compendium_campaign_reveals')
            ->select('entity_id')
            ->where('campaign_id', $campaignId)
            ->whereIn('entity_id', $pending)
            ->where('revoked_at', null);
        $characterId = (int) ($context['characterBestiaryCharacterId'] ?? 0);
        if ($characterId > 0) {
            $builder->where('character_id', $characterId);
        } else {
            $builder->where('character_id', null)
                ->groupStart()
                ->where('user_id', null)
                ->orWhere('user_id', $userId)
                ->groupEnd();
        }
        foreach ($builder->groupBy('entity_id')->get()->getResultArray() as $row) {
            $readable[(int) $row['entity_id']] = true;
        }
        return $readable;
    }

    private function assets(array $context, int $entityId, int $revisionId): array
    {
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        $universeId = (int) ($context['world']['universe_id'] ?? 0);
        return array_map(static function (array $row) use ($campaignId, $universeId): array {
            $available = !empty($row['storage_key']) || !empty($row['media_asset_id']);
            $query = $campaignId ? 'campaignId=' . $campaignId : 'universeId=' . $universeId;
            return ['id' => (int) $row['id'], 'filename' => $row['filename'], 'role' => $row['role'],
                'downloadStatus' => $row['download_status'], 'available' => $available,
                'mimeType' => $row['mime_type'], 'byteSize' => $row['byte_size'] === null ? null : (int) $row['byte_size'],
                'fileUrl' => $available ? '/api/compendium-corpus-assets/' . (int) $row['id'] . '/file?' . $query : null,
                'author' => $row['author'], 'license' => $row['license'], 'error' => $row['error_message']];
        }, $this->db->table('compendium_entity_assets ea')->select('a.*')
            ->join('compendium_corpus_assets a', 'a.id=ea.asset_id', 'inner')
            ->where('ea.entity_id', $entityId)->where('ea.source_revision_id', $revisionId)
            ->orderBy('ea.sort_order')->get()->getResultArray());
    }

    private function mechanics(array $context, int $entityId): array
    {
        $builder = $this->db->table('compendium_mechanical_profiles p')->select('p.*,s.code AS system_code')
            ->join('rpg_systems s', 's.id=p.system_id', 'inner')->where('p.entity_id', $entityId);
        if (empty($context['canSeeGm'])) $builder->where('p.usable', 1)->where('p.status', 'verified');
        return array_map(function (array $row): array {
            return ['id' => (int) $row['id'], 'systemId' => (int) $row['system_id'], 'systemCode' => $row['system_code'],
                'kind' => $row['kind'], 'variant' => $row['variant'], 'status' => $row['status'],
                'usable' => !empty($row['usable']), 'profile' => $this->json($row['profile_json']),
                'linkedResourceType' => $row['linked_resource_type'], 'linkedResourceId' => $row['linked_resource_id']];
        }, $builder->get()->getResultArray());
    }

    private function sourceIdsInHtml(string $html): array
    {
        preg_match_all('/data-compendium-source-id=["\']([^"\']+)["\']/i', $html, $matches);
        return array_fill_keys(array_values(array_unique($matches[1] ?? [])), true);
    }

    private function campaignNotes(array $context, int $entityId, bool $playersOnly = false): array
    {
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        if (!$campaignId) return [];
        $builder = $this->db->table('compendium_campaign_notes')->where('campaign_id', $campaignId)
            ->where('entity_id', $entityId)->where('deleted_at', null);
        if ($playersOnly) $builder->where('visibility', 'player');
        return array_map(static function (array $row): array {
            return ['id' => (int) $row['id'], 'body' => $row['body'], 'visibility' => $row['visibility'],
                'revision' => (int) $row['revision'], 'updatedAt' => $row['updated_at']];
        }, $builder->orderBy('updated_at', 'DESC')->get()->getResultArray());
    }

    private function revealPresentation(array $context, int $entityId): ?array
    {
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        if (!$campaignId) return null;
        $row = $this->db->table('compendium_campaign_reveals')->where('campaign_id', $campaignId)
            ->where('entity_id', $entityId)->where('character_id', null)
            ->where('revoked_at', null)->orderBy('created_at', 'DESC')->get()->getRowArray();
        return $row ? ['id' => (int) $row['id'], 'sourceRevisionId' => (int) $row['source_revision_id'],
            'userId' => $row['user_id'] === null ? null : (int) $row['user_id'],
            'sectionKeys' => $row['section_keys_json'] === null ? null : $this->json($row['section_keys_json']),
            'createdAt' => $row['created_at']] : null;
    }

    private function activityRow(array $context, int $entityId): ?array
    {
        $campaignId = (int) ($context['campaign']['id'] ?? 0);
        $userId = (int) ($context['auth']['user_id'] ?? 0);
        if (!$campaignId || !$userId) return null;
        return $this->db->table('compendium_user_activity')->where('campaign_id', $campaignId)
            ->where('user_id', $userId)->where('entity_id', $entityId)->get()->getRowArray() ?: null;
    }

    private function departments(array $entity, array $categories = []): array
    {
        $type = (string) ($entity['type_code'] ?? 'lore');
        $result = [];
        foreach (self::DEPARTMENT_TYPES as $department => $types) {
            if (in_array($type, $types, true)) $result[] = $department;
        }
        $haystack = CompendiumSearchNormalizer::normalize(implode(' ', array_merge([
            (string) ($entity['name'] ?? ''), (string) ($entity['aliases_normalized'] ?? ''),
        ], $categories)));
        foreach (self::DEPARTMENT_KEYWORDS as $department => $needles) {
            foreach ($needles as $needle) {
                if (strpos($haystack, $needle) !== false) {
                    $result[] = $department;
                    break;
                }
            }
        }
        $result[] = 'sources';
        return array_values(array_unique($result ?: ['world_atlas', 'sources']));
    }

    private function npcKind(array $departments, $candidate): ?string
    {
        $candidate = (string) $candidate;
        if (in_array($candidate, ['named', 'generic'], true)) {
            return $candidate;
        }
        return in_array('characters', $departments, true) ? 'named' : null;
    }

    private function departmentDefinitions(): array
    {
        return [
            'world_atlas' => 'Świat i Atlas', 'history' => 'Historia', 'characters' => 'Postacie',
            'bestiary' => 'Bestiariusz', 'factions' => 'Frakcje', 'religion' => 'Religia',
            'chaos' => 'Chaos', 'magic' => 'Magia', 'mechanics' => 'Mechanika WFRP2',
            'equipment' => 'Ekwipunek', 'gm_tools' => 'Narzędzia MG', 'maps' => 'Mapy', 'sources' => 'Źródła',
        ];
    }

    private function departmentCounts(
        int $worldId,
        string $accessSql,
        array $accessBindings
    ): array {
        $selects = [
            'COUNT(*) AS record_count',
            "SUM(CASE WHEN entity_rows.haystack LIKE '%generyczni%' THEN 1 ELSE 0 END) AS generic_npc_count",
        ];
        foreach ($this->departmentDefinitions() as $department => $_label) {
            if ($department === 'sources') {
                $condition = '1=1';
            } else {
                $conditions = [];
                if (isset(self::DEPARTMENT_TYPES[$department])) {
                    $conditions[] = 'entity_rows.type_code IN ('
                        . implode(',', array_map(
                            [$this->db, 'escape'],
                            self::DEPARTMENT_TYPES[$department]
                        ))
                        . ')';
                }
                foreach (self::DEPARTMENT_KEYWORDS[$department] ?? [] as $keyword) {
                    $conditions[] = 'entity_rows.haystack LIKE '
                        . $this->db->escape('%' . $keyword . '%');
                }
                $condition = $conditions ? implode(' OR ', $conditions) : '0=1';
            }
            $selects[] = 'SUM(CASE WHEN (' . $condition . ') THEN 1 ELSE 0 END) AS department_'
                . $department;
        }
        $sql = 'SELECT ' . implode(',', $selects) . ' FROM ('
            . 'SELECT ce.id,ce.type_code,CONCAT_WS(\' \',ce.normalized_name,'
            . "COALESCE(ce.aliases_normalized,''),"
            . "COALESCE(GROUP_CONCAT(DISTINCT c.normalized_name SEPARATOR ' '),'')) AS haystack "
            . 'FROM compendium_entities ce '
            . 'LEFT JOIN compendium_entity_categories ec ON ec.entity_id=ce.id '
            . 'LEFT JOIN compendium_source_categories c ON c.id=ec.category_id '
            . "WHERE ce.world_id=? AND ce.deleted_at IS NULL AND ({$accessSql}) "
            . 'GROUP BY ce.id,ce.type_code,ce.normalized_name,ce.aliases_normalized'
            . ') entity_rows';
        $row = $this->db->query(
            $sql,
            array_merge([$worldId], $accessBindings)
        )->getRowArray() ?: [];
        $result = [
            '_total' => (int) ($row['record_count'] ?? 0),
            '_generic_npcs' => (int) ($row['generic_npc_count'] ?? 0),
        ];
        foreach ($this->departmentDefinitions() as $department => $_label) {
            $result[$department] = (int) (
                $row['department_' . $department] ?? 0
            );
        }
        return $result;
    }

    private function campaignSystemId(array $context): ?int
    {
        if (!empty($context['campaign']['rpg_system_id'])) return (int) $context['campaign']['rpg_system_id'];
        $universe = $this->db->table('rpg_universes')->where('id', (int) $context['world']['universe_id'])->get()->getRowArray();
        return !empty($universe['default_system_id']) ? (int) $universe['default_system_id'] : null;
    }

    private function requiredCampaignId(array $context): int
    {
        $id = (int) ($context['campaign']['id'] ?? 0);
        if (!$id) throw new CampaignException('campaign_required', 'A campaign context is required.', 409);
        return $id;
    }

    private function requireCampaignManager(array $context): void
    {
        $this->requiredCampaignId($context);
        if (empty($context['canSeeGm'])) throw new CampaignException('forbidden', 'Campaign GM access is required.', 403);
    }

    private function json($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
