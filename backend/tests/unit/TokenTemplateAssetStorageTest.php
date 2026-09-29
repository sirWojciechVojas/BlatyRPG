<?php

use App\Services\Token\TokenException;
use App\Services\Token\TokenTemplateAssetStorage;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenTemplateAssetStorageTest extends CIUnitTestCase
{
    private $paths = [];

    protected function tearDown(): void
    {
        usort($this->paths, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($this->paths as $path) {
            if (is_file($path)) unlink($path);
            elseif (is_dir($path)) rmdir($path);
        }
        parent::tearDown();
    }

    /** @dataProvider supportedImages */
    public function testStoresSupportedImagesAsPrivateImmutableFiles(
        string $name,
        string $mime,
        string $contents,
        string $extension
    ): void {
        $directory = $this->temporaryDirectory();
        $source = $this->temporaryFile($contents);
        $stored = (new TokenTemplateAssetStorage($directory))->store(
            new TokenTemplateStorageUploadedFile($source, $name, $mime)
        );

        $this->paths[] = $directory . DIRECTORY_SEPARATOR . $stored['storage_key'];
        $this->assertSame($mime === 'image/x-webp' ? 'image/webp' : $mime, $stored['mime_type']);
        $this->assertStringEndsWith('.' . $extension, $stored['storage_key']);
        $this->assertGreaterThan(0, $stored['width']);
        $this->assertGreaterThan(0, $stored['height']);
        $this->assertFileExists($directory . DIRECTORY_SEPARATOR . $stored['storage_key']);
    }

    public function testRejectsUnsupportedMimeAndFilesOverTwentyFiveMegabytes(): void
    {
        $directory = $this->temporaryDirectory();
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        foreach ([
            ['malware.svg', 'image/svg+xml', strlen($png)],
            ['large.png', 'image/png', 26214401],
        ] as [$name, $mime, $size]) {
            $source = $this->temporaryFile($png);
            try {
                (new TokenTemplateAssetStorage($directory))->store(
                    new TokenTemplateStorageUploadedFile($source, $name, $mime, $size)
                );
                $this->fail('The invalid upload should be rejected.');
            } catch (TokenException $exception) {
                $this->assertSame(422, $exception->status());
                $this->assertSame('invalid_token_template_asset', $exception->errorCode());
            }
        }
    }

    public static function supportedImages(): array
    {
        return [
            'png' => [
                'token.png', 'image/png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
                'png',
            ],
            'gif' => [
                'token.gif', 'image/gif',
                base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true),
                'gif',
            ],
            'webp alias' => [
                'token.webp', 'image/x-webp',
                base64_decode('UklGRhwAAABXRUJQVlA4TA8AAAAvAUAAEAcQ/Y8CBiKi/wEA', true),
                'webp',
            ],
        ];
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'token-template-assets-' . bin2hex(random_bytes(8));
        mkdir($path, 0770, true);
        $this->paths[] = $path;
        return $path;
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'token-template-upload-');
        file_put_contents($path, $contents);
        $this->paths[] = $path;
        return $path;
    }
}

final class TokenTemplateStorageUploadedFile extends UploadedFile
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

    public function move(string $targetPath, ?string $name = null, bool $overwrite = false)
    {
        $destination = rtrim($targetPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ($name ?: $this->getName());
        if (!$overwrite && is_file($destination)) return false;
        if (!rename($this->getTempName(), $destination)) return false;
        $this->hasMoved = true;
        return true;
    }
}
