<?php

namespace App\Commands;

use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/** Idempotent deployment bridge from legacy private disks to media_assets. */
final class MigrateLegacyMedia extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'media:migrate-legacy';
    protected $description = 'Migrates application-managed legacy files to the central media registry.';
    protected $usage = 'media:migrate-legacy [--dry-run] [--module name] [--batch 100] [--verify] [--purge-local]';
    protected $options = [
        '--dry-run' => 'Report work without uploading or changing records.',
        '--module' => 'Only process one module: audio, map-creator, handouts, compendium, corpus, token-templates, professions, scenes.',
        '--batch' => 'Maximum records processed in this run (default 100).',
        '--verify' => 'Verify provider objects for already linked rows.',
        '--purge-local' => 'After successful provider verification, remove the legacy local source.',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('media_assets') || !$db->fieldExists('media_asset_id', 'handout_assets')) {
            CLI::error('The media library migration has not been applied.');
            return EXIT_ERROR;
        }
        $admin = $db->table('users')->where('role', 'admin')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if (!$admin) {
            CLI::error('An administrator account is required for provider uploads.');
            return EXIT_ERROR;
        }
        $auth = ['user_id' => (int) $admin['id'], 'role' => 'admin', 'anonymous' => false];
        $module = strtolower(trim((string) (CLI::getOption('module') ?? '')));
        $batch = max(1, min(1000, (int) (CLI::getOption('batch') ?? 100)));
        $dryRun = CLI::getOption('dry-run') !== null;
        $verify = CLI::getOption('verify') !== null || CLI::getOption('purge-local') !== null;
        $purge = CLI::getOption('purge-local') !== null;
        $definitions = $this->definitions();
        if ($module !== '' && !isset($definitions[$module])) {
            CLI::error('Unknown module: ' . $module);
            return EXIT_ERROR;
        }
        $media = new MediaService($db);
        $summary = ['examined' => 0, 'migrated' => 0, 'verified' => 0, 'purged' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($definitions as $name => $definition) {
            if ($module !== '' && $module !== $name) continue;
            if ($summary['examined'] >= $batch) break;
            if (!$db->tableExists($definition['table'])) continue;
            $remaining = $batch - $summary['examined'];
            $rows = $definition['rows']($db, $remaining, $verify);
            foreach ($rows as $row) {
                $summary['examined']++;
                $path = $definition['path']($row);
                $label = $name . '#' . ($row['id'] ?? ($row['legacy_key'] ?? '?'));
                $mediaId = (int) ($row['media_asset_id'] ?? 0);
                try {
                    if ($mediaId > 0) {
                        if ($verify) {
                            if ($dryRun) {
                                $summary['verified']++;
                                CLI::write('[dry-run] ' . $label . ': would verify media #' . $mediaId);
                                continue;
                            }
                            $media->verify($mediaId);
                            $this->markVerified($db, $mediaId);
                            $summary['verified']++;
                            if ($purge) {
                                if (is_file($path)) {
                                    unlink($path);
                                    $summary['purged']++;
                                }
                                if (isset($row['metadata_path']) && is_file($row['metadata_path'])) unlink($row['metadata_path']);
                                if (isset($definition['clear'])) $definition['clear']($db, $row);
                            }
                        } else {
                            $summary['skipped']++;
                        }
                        CLI::write(($dryRun ? '[dry-run] ' : '') . $label . ': already linked to media #' . $mediaId);
                        continue;
                    }
                    if (!is_file($path)) {
                        $summary['skipped']++;
                        CLI::write($label . ': local source missing, skipped', 'yellow');
                        continue;
                    }
                    if ($dryRun) {
                        $summary['migrated']++;
                        CLI::write('[dry-run] ' . $label . ' -> ' . $path);
                        continue;
                    }
                    $input = $definition['input']($row, $admin);
                    $uploaded = $media->uploadFile($auth, $path, $input);
                    // A remote object must be observable before a legacy row can
                    // point at it. --verify additionally checks pre-linked rows.
                    $media->verify((int) $uploaded['id']);
                    $this->markVerified($db, (int) $uploaded['id']);
                    $summary['verified']++;
                    $definition['link']($db, $row, (int) $uploaded['id']);
                    $summary['migrated']++;
                    if ($verify) {
                        if ($purge) {
                            if (is_file($path)) {
                                unlink($path);
                                $summary['purged']++;
                            }
                            if (isset($row['metadata_path']) && is_file($row['metadata_path'])) unlink($row['metadata_path']);
                            if (isset($definition['clear'])) $definition['clear']($db, $row);
                        }
                    }
                    CLI::write($label . ' -> media #' . (int) $uploaded['id'], 'green');
                } catch (MediaException $exception) {
                    $summary['failed']++;
                    CLI::error($label . ': ' . $exception->errorCode() . ' — ' . $exception->getMessage());
                } catch (\Throwable $exception) {
                    $summary['failed']++;
                    CLI::error($label . ': ' . $exception->getMessage());
                }
            }
        }
        CLI::newLine();
        CLI::write('examined=' . $summary['examined'] . ' migrated=' . $summary['migrated']
            . ' verified=' . $summary['verified'] . ' purged=' . $summary['purged']
            . ' skipped=' . $summary['skipped'] . ' failed=' . $summary['failed']);
        return $summary['failed'] > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }

    private function definitions(): array
    {
        $simpleRows = static function (string $table) {
            return static function ($db, int $limit, bool $verify = false) use ($table): array {
                $builder = $db->table($table . ' legacy')->select('legacy.*')
                    ->join('media_assets migration_media', 'migration_media.id=legacy.media_asset_id', 'left')
                    ->where('legacy.storage_key IS NOT NULL', null, false)->where('legacy.storage_key !=', '');
                if ($verify) {
                    $builder->groupStart()->where('legacy.media_asset_id', null)
                        ->orWhere("JSON_EXTRACT(migration_media.metadata, '$.legacy_verified_at') IS NULL", null, false)
                        ->groupEnd();
                }
                return $builder->orderBy('legacy.media_asset_id IS NULL', 'DESC', false)
                    ->orderBy('legacy.id')->limit($limit)->get()->getResultArray();
            };
        };
        $link = static function (string $table) {
            return static fn ($db, array $row, int $mediaId) => $db->table($table)
                ->where('id', (int) $row['id'])->where('media_asset_id', null)->update(['media_asset_id' => $mediaId]);
        };
        $private = static fn (string $category, string $nameField, string $ownerField): callable => static fn (array $row, array $admin): array => [
            'filename' => $row[$nameField] ?? basename((string) $row['storage_key']),
            'name' => pathinfo((string) ($row[$nameField] ?? basename((string) $row['storage_key'])), PATHINFO_FILENAME),
            'mimeType' => $row['mime_type'] ?? null,
            'category' => $category,
            'visibility' => 'private',
            'ownerUserId' => !empty($row[$ownerField]) ? (int) $row[$ownerField] : (int) $admin['id'],
        ];
        $clear = static function (string $table) {
            return static fn ($db, array $row) => $db->table($table)
                ->where('id', (int) $row['id'])->update(['storage_key' => null]);
        };
        return [
            'audio' => [
                'table' => 'audio_tracks', 'rows' => $simpleRows('audio_tracks'),
                'path' => static fn (array $row): string => WRITEPATH . 'uploads/audio/' . $row['storage_key'],
                'input' => $private('audio', 'original_name', 'owner_user_id'), 'link' => $link('audio_tracks'),
                'clear' => $clear('audio_tracks'),
            ],
            'map-creator' => [
                'table' => 'map_assets', 'rows' => $simpleRows('map_assets'),
                'path' => static fn (array $row): string => WRITEPATH . 'uploads/map-builder/' . $row['campaign_id'] . '/' . $row['storage_key'],
                'input' => static fn (array $row, array $admin): array => [
                    'filename' => $row['original_name'], 'name' => $row['name'], 'mimeType' => $row['mime_type'],
                    'category' => 'map-creator', 'visibility' => 'campaign', 'campaignId' => (int) $row['campaign_id'],
                    'ownerUserId' => (int) ($row['created_by_user_id'] ?: $admin['id']),
                ],
                'link' => $link('map_assets'), 'clear' => $clear('map_assets'),
            ],
            'handouts' => [
                'table' => 'handout_assets', 'rows' => $simpleRows('handout_assets'),
                'path' => static fn (array $row): string => WRITEPATH . 'uploads/handouts/' . $row['storage_key'],
                'input' => $private('documents', 'original_name', 'owner_user_id'), 'link' => $link('handout_assets'),
                'clear' => $clear('handout_assets'),
            ],
            'compendium' => [
                'table' => 'compendium_assets', 'rows' => $simpleRows('compendium_assets'),
                'path' => static fn (array $row): string => WRITEPATH . 'uploads/compendium/' . $row['storage_key'],
                'input' => $private('documents', 'original_name', 'uploaded_by_user_id'), 'link' => $link('compendium_assets'),
                'clear' => $clear('compendium_assets'),
            ],
            'corpus' => [
                'table' => 'compendium_corpus_assets', 'rows' => $simpleRows('compendium_corpus_assets'),
                'path' => static fn (array $row): string => WRITEPATH . 'uploads/compendium-corpus/' . $row['storage_key'],
                'input' => static fn (array $row, array $admin): array => [
                    'filename' => $row['filename'], 'name' => pathinfo((string) $row['filename'], PATHINFO_FILENAME),
                    'mimeType' => $row['mime_type'] ?? null, 'category' => 'documents',
                    'visibility' => 'private', 'ownerUserId' => (int) $admin['id'],
                ],
                'link' => $link('compendium_corpus_assets'), 'clear' => $clear('compendium_corpus_assets'),
            ],
            'token-templates' => [
                'table' => 'token_template_assets', 'rows' => $simpleRows('token_template_assets'),
                'path' => static fn (array $row): string => WRITEPATH . 'uploads/token-templates/' . $row['storage_key'],
                'input' => $private('characters', 'original_name', 'created_by_user_id'), 'link' => $link('token_template_assets'),
                'clear' => $clear('token_template_assets'),
            ],
            'professions' => [
                'table' => 'profession_assets', 'rows' => $simpleRows('profession_assets'),
                'path' => static fn (array $row): string => WRITEPATH . 'uploads/professions/' . $row['storage_key'],
                'input' => $private('characters', 'original_name', 'created_by_user_id'), 'link' => $link('profession_assets'),
                'clear' => $clear('profession_assets'),
            ],
            'scenes' => $this->sceneDefinition(),
        ];
    }

    private function sceneDefinition(): array
    {
        return [
            'table' => 'scene_media_assets',
            'rows' => static function ($db, int $limit, bool $verify = false): array {
                $rows = [];
                foreach (glob(WRITEPATH . 'uploads/scenes/*/*.json') ?: [] as $metadataPath) {
                    $metadata = json_decode((string) @file_get_contents($metadataPath), true);
                    $campaignId = (int) basename(dirname($metadataPath));
                    $key = is_array($metadata) ? (string) ($metadata['key'] ?? '') : '';
                    if ($campaignId < 1 || $key === '') continue;
                    $link = $db->table('scene_media_assets')->where('campaign_id', $campaignId)->where('legacy_key', $key)->get()->getRowArray();
                    if ($verify && !empty($link['media_asset_id'])) {
                        $verified = $db->table('media_assets')
                            ->where('id', (int) $link['media_asset_id'])
                            ->where("JSON_EXTRACT(metadata, '$.legacy_verified_at') IS NOT NULL", null, false)
                            ->countAllResults() > 0;
                        if ($verified) continue;
                    }
                    $rows[] = ($link ?: []) + $metadata + [
                        'id' => $link['id'] ?? null, 'campaign_id' => $campaignId, 'legacy_key' => $key,
                        'media_asset_id' => $link['media_asset_id'] ?? null, 'metadata_path' => $metadataPath,
                    ];
                }
                usort($rows, static fn (array $left, array $right): int =>
                    ((int) empty($right['media_asset_id'])) <=> ((int) empty($left['media_asset_id']))
                );
                return array_slice($rows, 0, $limit);
            },
            'path' => static fn (array $row): string => WRITEPATH . 'uploads/scenes/' . $row['campaign_id'] . '/' . $row['legacy_key'],
            'input' => static fn (array $row, array $admin): array => [
                'filename' => $row['name'] ?? $row['legacy_key'], 'name' => pathinfo((string) ($row['name'] ?? $row['legacy_key']), PATHINFO_FILENAME),
                'mimeType' => $row['mimeType'] ?? null, 'category' => 'maps', 'visibility' => 'campaign',
                'campaignId' => (int) $row['campaign_id'], 'ownerUserId' => (int) $admin['id'],
            ],
            'link' => static function ($db, array $row, int $mediaId): void {
                $db->query(
                    'INSERT INTO scene_media_assets (campaign_id, legacy_key, media_asset_id, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE media_asset_id=VALUES(media_asset_id), updated_at=VALUES(updated_at)',
                    [(int) $row['campaign_id'], $row['legacy_key'], $mediaId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
                );
            },
        ];
    }

    private function markVerified($db, int $mediaId): void
    {
        $db->query(
            "UPDATE media_assets SET metadata=JSON_SET(COALESCE(metadata, JSON_OBJECT()), '$.legacy_verified_at', ?) WHERE id=?",
            [date(DATE_ATOM), $mediaId]
        );
    }
}
