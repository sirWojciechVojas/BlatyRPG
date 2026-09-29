<?php

use App\Services\Handout\HandoutAssetStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class HandoutAssetStorageTest extends CIUnitTestCase
{
    private $temporaryPaths = [];

    protected function tearDown(): void
    {
        usort($this->temporaryPaths, static function (string $left, string $right): int {
            return strlen($right) <=> strlen($left);
        });
        foreach ($this->temporaryPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            }
        }

        parent::tearDown();
    }

    public function testStoresWebpReportedWithHistoricalMimeAlias(): void
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'handout-storage-' . bin2hex(random_bytes(8));
        mkdir($basePath, 0770, true);
        $this->temporaryPaths[] = $basePath;

        $sourcePath = $basePath . DIRECTORY_SEPARATOR . 'upload.tmp';
        file_put_contents(
            $sourcePath,
            base64_decode('UklGRhwAAABXRUJQVlA4TA8AAAAvAUAAEAcQ/Y8CBiKi/wEA', true)
        );
        $this->temporaryPaths[] = $sourcePath;

        $file = new HandoutAssetStorageUploadedFile($sourcePath, 'clue.webp', 'image/x-webp');
        $stored = (new HandoutAssetStorage($basePath))->store(7, $file);

        $this->assertSame('image/webp', $stored['mimeType']);
        $this->assertSame('clue.webp', $stored['originalName']);
        $this->assertStringEndsWith('.webp', $stored['storageKey']);
        $this->assertFileExists($stored['path']);

        $storedDirectory = dirname($stored['path']);
        $ownerDirectory = dirname($storedDirectory);
        $this->temporaryPaths[] = $stored['path'];
        $this->temporaryPaths[] = $storedDirectory;
        $this->temporaryPaths[] = $ownerDirectory;
    }
}

final class HandoutAssetStorageUploadedFile extends UploadedFile
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
        parent::__construct($path, $originalName, $mimeType, $size ?? filesize($path), $error ?? UPLOAD_ERR_OK, $clientPath);
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
        $destination = rtrim($targetPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ($name ?: $this->getName());
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
