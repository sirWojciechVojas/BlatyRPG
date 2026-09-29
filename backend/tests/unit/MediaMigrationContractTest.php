<?php

use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class MediaMigrationContractTest extends CIUnitTestCase
{
    public function testRegistryContainsProviderMetadataAndAccessScope(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-27-120000_CreateMediaAssets.php'
        );

        foreach ([
            'provider', 'provider_asset_id', 'public_id', 'resource_type', 'category',
            'mime_type', 'file_size', 'width', 'height', 'duration', 'visibility',
            'metadata', 'owner_user_id', 'campaign_id',
        ] as $field) {
            $this->assertStringContainsString("'{$field}'", $source);
        }
        $this->assertStringNotContainsString("'url' =>", $source);
        $this->assertStringContainsString('uq_media_assets_provider_asset', $source);
    }

    public function testProviderContainerMigrationBackfillsCloudAndR2Buckets(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-27-122000_AddProviderContainerToMediaAssets.php'
        );

        $this->assertStringContainsString("'provider_container'", $source);
        $this->assertStringContainsString("getenv('R2_PUBLIC_BUCKET')", $source);
        $this->assertStringContainsString("getenv('R2_PRIVATE_BUCKET')", $source);
        $this->assertStringContainsString('idx_media_assets_provider_container', $source);
    }

    public function testCharacterAssetsAreMigratedToCentralRelation(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-27-121000_LinkCharacterAssetsToMediaAssets.php'
        );

        $this->assertStringContainsString("'media_asset_id'", $source);
        $this->assertStringContainsString('fk_character_assets_media_asset', $source);
        $this->assertStringContainsString("'migrated_from' => 'character_assets'", $source);
    }
}
