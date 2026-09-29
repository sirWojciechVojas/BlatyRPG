<?php

use App\Services\Profession\ProfessionAssetStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ProfessionAssetStorageTest extends CIUnitTestCase
{
    private $paths = [];

    protected function tearDown(): void
    {
        usort(
            $this->paths,
            static fn (string $left, string $right): int =>
                strlen($right) <=> strlen($left)
        );
        foreach ($this->paths as $path) {
            if (is_file($path)) unlink($path);
            elseif (is_dir($path)) rmdir($path);
        }
        parent::tearDown();
    }

    public function testStoresAValidatedPrivateFullBodyImage(): void
    {
        $directory = $this->temporaryDirectory();
        $source = $this->temporaryFile(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        ));
        $stored = (new ProfessionAssetStorage($directory))->store(
            new ProfessionAssetUploadedFile($source, 'arcykaplan.png', 'image/png')
        );
        $storedPath = $directory . DIRECTORY_SEPARATOR . $stored['storage_key'];
        $this->paths[] = $storedPath;

        $this->assertSame('image/png', $stored['mime_type']);
        $this->assertSame(1, $stored['width']);
        $this->assertSame(1, $stored['height']);
        $this->assertFileExists($storedPath);
    }

    public function testRejectsUnsupportedImageTypes(): void
    {
        $directory = $this->temporaryDirectory();
        $source = $this->temporaryFile('<svg></svg>');

        $this->expectException(\InvalidArgumentException::class);
        (new ProfessionAssetStorage($directory))->store(
            new ProfessionAssetUploadedFile($source, 'figure.svg', 'image/svg+xml')
        );
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'profession-assets-' . bin2hex(random_bytes(8));
        mkdir($path, 0770, true);
        $this->paths[] = $path;
        return $path;
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'profession-upload-');
        file_put_contents($path, $contents);
        $this->paths[] = $path;
        return $path;
    }
}

final class ProfessionAssetUploadedFile extends UploadedFile
{
    private $detectedMime;

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
        $this->detectedMime = $mimeType ?: 'application/octet-stream';
    }

    public function isValid(): bool
    {
        return true;
    }

    public function getMimeType(): string
    {
        return $this->detectedMime;
    }

    public function move(
        string $targetPath,
        ?string $name = null,
        bool $overwrite = false
    ) {
        $destination = rtrim($targetPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . ($name ?: $this->getName());
        if (!$overwrite && is_file($destination)) return false;
        if (!rename($this->getTempName(), $destination)) return false;
        $this->hasMoved = true;
        return true;
    }
}
