<?php

use App\Services\Campaign\CampaignAccessService;
use App\Services\Scene\SceneAssetService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class SceneAssetServiceTest extends CIUnitTestCase
{
    private $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'scene-assets-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0770, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
        parent::tearDown();
    }

    public function testStoresListsAndDownloadsCampaignMapImage(): void
    {
        $sourcePath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'source.png';
        file_put_contents(
            $sourcePath,
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true
            )
        );
        $file = new SceneAssetUploadedFile($sourcePath, 'Mapa ruin.png', 'image/png');
        $storagePath = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'storage';
        $service = new SceneAssetService(new SceneAssetAccessService(), $storagePath);

        $uploaded = $service->upload(7, ['user_id' => 3], $file)['asset'];

        $this->assertSame('Mapa ruin.png', $uploaded['name']);
        $this->assertSame('image/png', $uploaded['mimeType']);
        $this->assertSame(1, $uploaded['width']);
        $this->assertSame(1, $uploaded['height']);
        $this->assertMatchesRegularExpression(
            '#^/api/campaigns/7/scene-assets/[a-f0-9]{32}\.png/file$#',
            $uploaded['url']
        );

        $listed = $service->list(7, ['user_id' => 3]);
        $this->assertCount(1, $listed['items']);
        $this->assertSame($uploaded['key'], $listed['items'][0]['key']);

        $download = $service->download(7, $uploaded['key'], ['user_id' => 3]);
        $this->assertFileExists($download['path']);
        $this->assertSame($uploaded['url'], $download['asset']['url']);
    }

    private function removeDirectory(?string $directory): void
    {
        if (!$directory || !is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}

final class SceneAssetAccessService extends CampaignAccessService
{
    public function forCampaign(array $auth, int $campaignId): array
    {
        return [
            'exists' => $campaignId === 7,
            'allowed' => true,
            'capabilities' => ['canManage' => true, 'canViewHidden' => true],
        ];
    }
}

final class SceneAssetUploadedFile extends UploadedFile
{
    private $detectedMimeType;

    public function __construct(
        string $path,
        string $originalName,
        ?string $mimeType = null,
        ?int $size = null,
        ?int $error = null,
        ?string $clientPath = null
    ) {
        parent::__construct(
            $path,
            $originalName,
            $mimeType,
            $size ?? filesize($path),
            $error ?? UPLOAD_ERR_OK,
            $clientPath
        );
        $this->detectedMimeType = $mimeType ?: 'application/octet-stream';
    }

    public function isValid(): bool
    {
        return true;
    }

    public function getMimeType(): string
    {
        return $this->detectedMimeType;
    }

    public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
    {
        $destination = rtrim($targetPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . ($name ?: $this->getName());
        if (!$overwrite && is_file($destination)) {
            return false;
        }
        if (!rename($this->getTempName(), $destination)) {
            return false;
        }
        $this->hasMoved = true;
        return true;
    }
}
