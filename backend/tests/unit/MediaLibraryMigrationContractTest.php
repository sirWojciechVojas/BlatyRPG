<?php

use App\Models\MediaAssetModel;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class MediaLibraryMigrationContractTest extends CIUnitTestCase
{
    public function testLibrarySchemaAddsEditableMetadataCollectionsAndRestrictedRelations(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-28-100000_CreateMediaLibrary.php'
        );

        foreach (['name', 'description', 'tags', 'custom_metadata', 'format', 'revision'] as $field) {
            $this->assertStringContainsString("'{$field}'", $source);
        }
        foreach (['media_collections', 'media_collection_assets', 'scene_media_assets'] as $table) {
            $this->assertStringContainsString($table, $source);
        }
        foreach ([
            'audio_tracks', 'map_assets', 'handout_assets', 'compendium_assets',
            'compendium_corpus_assets', 'token_template_assets', 'profession_assets',
        ] as $table) {
            $this->assertStringContainsString("'{$table}'", $source);
        }
        $this->assertStringContainsString('ON DELETE RESTRICT', $source);
        $this->assertStringContainsString("'relocating'", file_get_contents(APPPATH . 'Models/MediaAssetModel.php'));
        $this->assertStringContainsString("'delete_failed'", file_get_contents(APPPATH . 'Models/MediaAssetModel.php'));
    }

    public function testCategoryTaxonomyIsClosedAndCharacterRolesAreNotCategories(): void
    {
        $this->assertSame([
            'characters', 'npcs', 'monsters', 'items', 'maps', 'textures',
            'map-creator', 'audio', 'video', 'documents', 'other',
        ], MediaAssetModel::CATEGORIES);
        $this->assertEmpty(array_intersect(
            ['avatar', 'portrait', 'token', 'fullbody'],
            MediaAssetModel::CATEGORIES
        ));
    }

    public function testDeploymentCommandExposesSafeResumeAndPurgeOptions(): void
    {
        $source = file_get_contents(APPPATH . 'Commands/MigrateLegacyMedia.php');
        foreach (['--dry-run', '--module', '--batch', '--verify', '--purge-local'] as $option) {
            $this->assertStringContainsString($option, $source);
        }
        $this->assertStringContainsString('media->verify', $source);
        $this->assertStringContainsString('media_asset_id', $source);
        $this->assertLessThan(
            strpos($source, '$definition[\'link\']'),
            strpos($source, '$media->verify((int) $uploaded[\'id\']);')
        );
    }

    public function testSceneTilesCanLinkDeduplicatedExternalMapReferences(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-28-120000_RegisterExternalSceneTileAssets.php'
        );

        foreach (['scene_tiles', 'media_asset_id', 'source_url', 'availability_status', 'ExternalMediaUrl'] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
        $this->assertStringContainsString('fk_scene_tiles_media_asset', $source);
        $this->assertStringContainsString('map_assets', $source);
        $this->assertStringContainsString('scene_media_assets', $source);
        $this->assertStringContainsString('ON DELETE RESTRICT', $source);
    }

    public function testExternalAudioTracksAreRegisteredWithoutDownloadingFiles(): void
    {
        $migration = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-28-130000_RegisterExternalAudioTracksAsMediaAssets.php'
        );
        $media = file_get_contents(APPPATH . 'Services/Media/MediaService.php');

        foreach (['audio_tracks', 'source_url', 'ExternalMediaUrl', "'audio'", 'media_asset_id'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }
        $this->assertStringContainsString("['image', 'audio', 'video']", $media);
        $this->assertStringNotContainsString('curl_', $migration);
    }
}
