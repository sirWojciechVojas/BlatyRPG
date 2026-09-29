<?php

namespace App\Commands;

use App\Services\Campaign\CampaignException;
use App\Services\Admin\AdminCompendiumService;
use App\Services\Admin\AdminException;
use App\Services\Compendium\CompendiumService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

final class AuditCompendium extends BaseCommand
{
    protected $group = 'Compendium';
    protected $name = 'compendium:audit';
    protected $description = 'Checks corpus completeness, immutable revisions, search and API read contracts.';
    protected $usage = 'compendium:audit [campaign-id]';

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $campaignId = max(1, (int) ($params[0] ?? 1));
        $admin = $db->table('users')->where('role', 'admin')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if (!$admin) {
            CLI::error('No administrator account is available for the read-contract audit.');
            return EXIT_ERROR;
        }
        $auth = ['user_id' => (int) $admin['id'], 'role' => 'admin', 'anonymous' => false];
        $service = new CompendiumService($db);
        $overview = $service->campaignOverview($campaignId, $auth);
        $adminService = new AdminCompendiumService($db);
        $adminOverview = $adminService->overview($auth);
        $wikiSource = $db->table('compendium_sources')->where('source_key', 'warhammerpl')->get()->getRowArray();
        $wikiSourceId = (int) ($wikiSource['id'] ?? 0);
        $checks = [];
        $checks['documents'] = $db->table('compendium_source_documents')->where('source_id', $wikiSourceId)->countAllResults();
        $checks['entities'] = $db->table('compendium_entities e')->join('compendium_source_documents d', 'd.id=e.source_document_id', 'inner')
            ->where('d.source_id', $wikiSourceId)->where('e.deleted_at', null)->countAllResults();
        $checks['currentRevisions'] = $db->table('compendium_source_documents')->where('source_id', $wikiSourceId)
            ->where('current_revision_id IS NOT NULL', null, false)->countAllResults();
        $checks['fullWikitext'] = $db->table('compendium_source_revisions r')->join('compendium_source_documents d', 'd.id=r.document_id', 'inner')
            ->where('d.source_id', $wikiSourceId)->where('r.raw_wikitext !=', '')->countAllResults();
        $checks['fullHtml'] = $db->table('compendium_source_revisions r')->join('compendium_source_documents d', 'd.id=r.document_id', 'inner')
            ->where('d.source_id', $wikiSourceId)->where('r.sanitized_html !=', '')->countAllResults();
        $checks['distinctExternalIds'] = (int) ($db->table('compendium_source_documents')->select('COUNT(DISTINCT external_id) AS n')
            ->where('source_id', $wikiSourceId)->get()->getRowArray()['n'] ?? 0);
        $checks['assetsRegistered'] = $db->table('compendium_corpus_assets')->countAllResults();
        $checks['overviewCorpusCount'] = (int) ($overview['corpus']['count'] ?? 0);
        $checks['overviewDepartments'] = count($overview['departments'] ?? []);
        $checks['departmentCounts'] = [];
        foreach ($overview['departments'] ?? [] as $department) {
            $checks['departmentCounts'][(string) $department['key']] = (int) $department['count'];
        }
        $checks['duplicateRevisions'] = (int) ($db->query(
            'SELECT COUNT(*) AS n FROM (SELECT document_id,external_revision_id,checksum,COUNT(*) AS c '
            . 'FROM compendium_source_revisions GROUP BY document_id,external_revision_id,checksum HAVING c>1) duplicates'
        )->getRowArray()['n'] ?? 0);
        $linkCounts = $db->query(
            'SELECT COUNT(*) AS total,SUM(l.status=\'resolved\') AS resolved,SUM(l.status!=\'resolved\') AS unresolved '
            . 'FROM compendium_wiki_links l JOIN compendium_entities ce ON ce.id=l.from_entity_id '
            . 'JOIN compendium_source_documents d ON d.id=ce.source_document_id '
            . 'WHERE d.source_id=? AND ce.current_source_revision_id=l.source_revision_id',
            [$wikiSourceId]
        )->getRowArray();
        $checks['documentLinks'] = (int) ($linkCounts['total'] ?? 0);
        $checks['resolvedDocumentLinks'] = (int) ($linkCounts['resolved'] ?? 0);
        $checks['unresolvedDocumentLinks'] = (int) ($linkCounts['unresolved'] ?? 0);
        $checks['redirectAliases'] = $db->table('compendium_entity_names')->where('kind', 'redirect')->countAllResults();
        $catalogSource = $db->table('compendium_sources')->where('source_key', 'blatyrpg-wfrp2-catalog')->get()->getRowArray();
        $catalogSourceId = (int) ($catalogSource['id'] ?? 0);
        $checks['wfrp2CatalogDocuments'] = $db->table('compendium_source_documents')->where('source_id', $catalogSourceId)->countAllResults();
        $profileCounts = $db->table('compendium_mechanical_profiles')->select(
            'COUNT(*) AS total, SUM(usable=1) AS usable, SUM(usable=0) AS unavailable', false
        )->get()->getRowArray();
        $checks['mechanicalProfiles'] = (int) ($profileCounts['total'] ?? 0);
        $checks['usableUnverifiedProfiles'] = (int) ($profileCounts['usable'] ?? 0);
        $checks['unavailableProfiles'] = (int) ($profileCounts['unavailable'] ?? 0);
        $checks['adminWorlds'] = count($adminOverview['worlds'] ?? []);
        $checks['adminRpgSystems'] = count($adminOverview['systems'] ?? []);
        $checks['adminWorldsWithDefaultSystem'] = count(array_filter(
            $adminOverview['worlds'] ?? [],
            static fn (array $world): bool => !empty($world['systemId'])
        ));
        $checks['adminEntries'] = (int) ($adminOverview['metrics']['entries'] ?? 0);
        $checks['adminImportsVisible'] = count($adminOverview['imports'] ?? []);
        $checks['adminSourcesVisible'] = count($adminOverview['sources'] ?? []);
        $checks += $this->auditAdminSurface($db, $adminService, $auth);

        $list = $service->campaignIndex($campaignId, $auth, ['q' => 'Altdorf', 'limit' => 10]);
        $deep = $service->campaignIndex($campaignId, $auth, ['q' => 'maminsynków nienadających', 'limit' => 10]);
        $withoutDiacritics = $service->campaignIndex($campaignId, $auth, ['q' => 'lowca czarownic', 'limit' => 10]);
        $redirect = $db->table('compendium_entity_names')->where('kind', 'redirect')->orderBy('id')->get()->getRowArray();
        $alias = $redirect ? $service->campaignIndex($campaignId, $auth, ['q' => $redirect['name'], 'limit' => 10]) : ['items' => []];
        $checks['searchNameHits'] = count($list['items']);
        $checks['searchBodyHits'] = count($deep['items']);
        $checks['searchWithoutDiacriticsHits'] = count($withoutDiacritics['items']);
        $checks['redirectAliasHits'] = count($alias['items']);
        $checks['listOmitsFullArticle'] = !$list['items'] || !array_key_exists('sourceHtml', $list['items'][0]);
        if ($list['items']) {
            $detail = $service->campaignShow($campaignId, (int) $list['items'][0]['id'], $auth)['entry'];
            $checks['detailHasFullHtml'] = !empty($detail['sourceHtml']);
            $checks['detailHasSourceRevision'] = !empty($detail['source']['revisionId']);
            $checks['detailHasSections'] = !empty($detail['sections']);
            $checks['detailHasDocumentLinks'] = !empty($detail['wikiLinks']);
        } else {
            $checks['detailHasFullHtml'] = false;
            $checks['detailHasSourceRevision'] = false;
            $checks['detailHasSections'] = false;
            $checks['detailHasDocumentLinks'] = false;
        }
        $checks += $this->auditPlayerBoundary($db, $service, $campaignId, $auth);
        $valid = $checks['documents'] === 2294 && $checks['entities'] === 2294
            && $checks['currentRevisions'] === 2294 && $checks['fullWikitext'] === 2294
            && $checks['fullHtml'] === 2294 && $checks['distinctExternalIds'] === 2294
            && $checks['overviewCorpusCount'] >= 2294 && $checks['overviewDepartments'] === 13
            && ($checks['departmentCounts']['bestiary'] ?? 0) >= 103
            && ($checks['departmentCounts']['sources'] ?? 0) === $checks['overviewCorpusCount']
            && $checks['documentLinks'] > 0 && $checks['resolvedDocumentLinks'] > 0
            && $checks['unresolvedDocumentLinks'] >= 0 && $checks['redirectAliases'] >= 189
            && $checks['adminWorlds'] > 0 && $checks['adminEntries'] >= 2294
            && $checks['adminRpgSystems'] > 0
            && $checks['adminWorldsWithDefaultSystem'] === $checks['adminWorlds']
            && $checks['adminImportsVisible'] > 0 && $checks['adminSourcesVisible'] > 0
            && $checks['adminPolicyWrite'] && $checks['adminRejectsUnsafeProfile']
            && $checks['nonAdminCannotUseAdminCompendium']
            && $checks['wfrp2CatalogDocuments'] === 434 && $checks['mechanicalProfiles'] === 434
            && $checks['usableUnverifiedProfiles'] === 0 && $checks['unavailableProfiles'] === 434
            && $checks['duplicateRevisions'] === 0 && $checks['searchNameHits'] > 0
            && $checks['searchBodyHits'] > 0 && $checks['searchWithoutDiacriticsHits'] > 0
            && $checks['redirectAliasHits'] > 0 && $checks['listOmitsFullArticle'] && $checks['detailHasFullHtml']
            && $checks['detailHasSourceRevision'] && $checks['detailHasSections']
            && $checks['detailHasDocumentLinks'] && $checks['playerBoundaryChecked']
            && $checks['playerCanReadReveal'] && $checks['playerPayloadHasNoGmKeys']
            && $checks['partialRevealHidesWholeRevisionMetadata'] && $checks['hiddenEntrySearchHits'] === 0
            && $checks['hiddenEntryDirectReadStatus'] === 404;
        CLI::write(json_encode(['valid' => $valid, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $valid ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function auditPlayerBoundary($db, CompendiumService $service, int $campaignId, array $managerAuth): array
    {
        $defaults = [
            'playerBoundaryChecked' => false, 'playerCanReadReveal' => false,
            'playerPayloadHasNoGmKeys' => false, 'partialRevealHidesWholeRevisionMetadata' => false,
            'hiddenEntrySearchHits' => -1, 'hiddenEntryDirectReadStatus' => 0,
        ];
        $member = $db->table('campaign_members cm')->select('cm.user_id,u.role')
            ->join('users u', 'u.id=cm.user_id', 'inner')->where('cm.campaign_id', $campaignId)
            ->whereIn('cm.role', ['player', 'observer'])->where('cm.is_active', 1)->where('cm.left_at', null)
            ->where('u.deleted_at', null)->orderBy('cm.id')->get()->getRowArray();
        if (!$member) return $defaults;
        $playerAuth = ['user_id' => (int) $member['user_id'], 'role' => (string) $member['role'], 'anonymous' => false];
        $campaign = $db->table('campaigns')->where('id', $campaignId)->get()->getRowArray();
        $world = $campaign ? $db->table('compendium_worlds')->where('universe_id', (int) $campaign['rpg_universe_id'])->get()->getRowArray() : null;
        if (!$world) return $defaults;
        $candidate = $db->table('compendium_entities ce')->select('ce.entry_id,ce.name,sr.sections_json')
            ->join('compendium_source_documents d', 'd.id=ce.source_document_id', 'inner')
            ->join('compendium_sources s', 's.id=d.source_id', 'inner')
            ->join('compendium_source_revisions sr', 'sr.id=ce.current_source_revision_id', 'inner')
            ->where('ce.world_id', (int) $world['id'])->where('s.source_key', 'warhammerpl')
            ->where('JSON_LENGTH(sr.sections_json) >', 1, false)->orderBy('ce.id')->get()->getRowArray();
        if (!$candidate) return $defaults;
        $hidden = $db->table('compendium_entities ce')->select('ce.entry_id,ce.name')
            ->join('compendium_source_documents d', 'd.id=ce.source_document_id', 'inner')
            ->join('compendium_sources s', 's.id=d.source_id', 'inner')
            ->where('ce.world_id', (int) $world['id'])->where('s.source_key', 'warhammerpl')
            ->where('ce.entry_id !=', (int) $candidate['entry_id'])
            ->where('NOT EXISTS (SELECT 1 FROM compendium_campaign_reveals cr WHERE cr.entity_id=ce.id AND cr.campaign_id=' . $campaignId
                . ' AND cr.character_id IS NULL AND cr.revoked_at IS NULL AND (cr.user_id IS NULL OR cr.user_id=' . (int) $member['user_id'] . '))', null, false)
            ->orderBy('CHAR_LENGTH(ce.name)', 'DESC', false)->get()->getRowArray();
        if (!$hidden) return $defaults;
        $sections = json_decode((string) $candidate['sections_json'], true) ?: [];
        $sectionKey = (string) ($sections[0]['id'] ?? '');
        if ($sectionKey === '') return $defaults;

        $db->transBegin();
        try {
            $service->campaignReveal($campaignId, (int) $candidate['entry_id'], $managerAuth, [
                'userId' => (int) $member['user_id'], 'sectionKeys' => [$sectionKey],
            ]);
            $visible = $service->campaignIndex($campaignId, $playerAuth, ['q' => $candidate['name'], 'limit' => 10]);
            $detail = $service->campaignShow($campaignId, (int) $candidate['entry_id'], $playerAuth)['entry'];
            $restricted = ['gmContent', 'gmFields', 'gmNotes', 'reveal', 'statBlocks'];
            $hasRestricted = (bool) array_intersect($restricted, array_keys($detail));
            $visibleLinkIds = [];
            preg_match_all('/data-compendium-source-id=["\']([^"\']+)["\']/i', (string) ($detail['sourceHtml'] ?? ''), $linkMatches);
            foreach ($linkMatches[1] ?? [] as $sourceId) $visibleLinkIds[(string) $sourceId] = true;
            $linksLimitedToSection = count(array_filter($detail['wikiLinks'] ?? [], static function (array $link) use ($visibleLinkIds): bool {
                return !isset($visibleLinkIds[(string) ($link['sourceId'] ?? '')]);
            })) === 0;
            $hiddenSearch = $service->campaignIndex($campaignId, $playerAuth, ['q' => $hidden['name'], 'limit' => 10]);
            $hiddenStatus = 200;
            try {
                $service->campaignShow($campaignId, (int) $hidden['entry_id'], $playerAuth);
            } catch (CampaignException $error) {
                $hiddenStatus = $error->status();
            }
            return [
                'playerBoundaryChecked' => true,
                'playerCanReadReveal' => count($visible['items']) > 0 && count($detail['sections'] ?? []) === 1,
                'playerPayloadHasNoGmKeys' => !$hasRestricted,
                'partialRevealHidesWholeRevisionMetadata' => $linksLimitedToSection
                    && empty($detail['wikiBacklinks']) && empty($detail['semanticRelations'])
                    && empty($detail['corpusAssets']) && empty($detail['categories']),
                'hiddenEntrySearchHits' => count($hiddenSearch['items']),
                'hiddenEntryDirectReadStatus' => $hiddenStatus,
            ];
        } catch (\Throwable $error) {
            return $defaults + ['playerBoundaryError' => $error->getMessage()];
        } finally {
            $db->transRollback();
        }
    }

    private function auditAdminSurface(
        $db,
        AdminCompendiumService $service,
        array $adminAuth
    ): array {
        $checks = [
            'adminPolicyWrite' => false,
            'adminRejectsUnsafeProfile' => false,
            'nonAdminCannotUseAdminCompendium' => false,
        ];
        $candidate = $db->table('compendium_entities e')
            ->select('e.entry_id,w.universe_id')
            ->join('compendium_worlds w', 'w.id=e.world_id', 'inner')
            ->where('e.deleted_at', null)->where('e.entry_id IS NOT NULL', null, false)
            ->orderBy('e.id')->get()->getRowArray();
        $profile = $db->table('compendium_mechanical_profiles p')
            ->select('p.id,w.universe_id')
            ->join('compendium_entities e', 'e.id=p.entity_id', 'inner')
            ->join('compendium_worlds w', 'w.id=e.world_id', 'inner')
            ->orderBy('p.id')->get()->getRowArray();
        if ($candidate) {
            $db->transBegin();
            try {
                $result = $service->updateEntryPolicy(
                    $adminAuth,
                    (int) $candidate['universe_id'],
                    (int) $candidate['entry_id'],
                    ['playerDescription' => 'Compendium administration audit']
                );
                $checks['adminPolicyWrite'] =
                    ($result['entryPolicy']['playerDescription'] ?? '') ===
                    'Compendium administration audit';
            } finally {
                $db->transRollback();
            }
        }
        if ($profile) {
            try {
                $service->updateMechanicalProfile(
                    $adminAuth,
                    (int) $profile['universe_id'],
                    (int) $profile['id'],
                    ['status' => 'unverified', 'usable' => true]
                );
            } catch (AdminException $exception) {
                $checks['adminRejectsUnsafeProfile'] = $exception->status() === 422;
            }
        }
        $user = $db->table('users')->where('role !=', 'admin')->where('deleted_at', null)
            ->orderBy('id')->get()->getRowArray();
        if ($user) {
            try {
                $service->overview([
                    'user_id' => (int) $user['id'],
                    'role' => (string) $user['role'],
                    'anonymous' => false,
                ]);
            } catch (AdminException $exception) {
                $checks['nonAdminCannotUseAdminCompendium'] = $exception->status() === 403;
            }
        }
        return $checks;
    }
}
