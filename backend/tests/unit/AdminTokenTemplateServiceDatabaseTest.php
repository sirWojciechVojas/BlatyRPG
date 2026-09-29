<?php

use App\Models\TokenTemplateAssetModel;
use App\Models\TokenTemplateModel;
use App\Services\Admin\AdminException;
use App\Services\Admin\AdminTokenTemplateService;
use App\Services\Token\TokenTemplateAssetStorage;
use App\Services\Token\TokenTemplatePayloadValidator;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

/** @internal */
final class AdminTokenTemplateServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $templateDb;
    private string $assetDirectory;
    private AdminTokenTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->templateDb->table('users')->insertBatch([
            ['id' => 1, 'role' => 'admin', 'deleted_at' => null],
            ['id' => 2, 'role' => 'user', 'deleted_at' => null],
        ]);
        $this->assetDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'admin-token-template-assets-' . bin2hex(random_bytes(8));
        mkdir($this->assetDirectory, 0770, true);
        $this->service = new AdminTokenTemplateService(
            $this->templateDb,
            new TokenTemplateModel($this->templateDb),
            new TokenTemplateAssetModel($this->templateDb),
            new TokenTemplatePayloadValidator(),
            new TokenTemplateAssetStorage($this->assetDirectory)
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->assetDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            if (is_file($path)) unlink($path);
        }
        if (is_dir($this->assetDirectory)) rmdir($this->assetDirectory);
        $this->templateDb->close();
        parent::tearDown();
    }

    public function testOnlyAdministratorsCanManageTemplates(): void
    {
        try {
            $this->service->list(['user_id' => 2]);
            $this->fail('A non-administrator must not read the administrative catalog.');
        } catch (AdminException $exception) {
            $this->assertSame(403, $exception->status());
        }
    }

    public function testCrudRequiresCurrentRevisionAndUsesSoftDeletion(): void
    {
        $created = $this->service->create($this->admin(), $this->payload());
        $this->assertSame(1, $created['template']['revision']);

        $updated = $this->service->update(
            $this->admin(),
            (int) $created['template']['id'],
            ['name' => 'Orc veteran', 'revision' => 1]
        );
        $this->assertSame('Orc veteran', $updated['template']['name']);
        $this->assertSame(2, $updated['template']['revision']);

        try {
            $this->service->update(
                $this->admin(),
                (int) $created['template']['id'],
                ['name' => 'Stale write', 'revision' => 1]
            );
            $this->fail('A stale revision must be rejected.');
        } catch (AdminException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame(2, $exception->details()['currentRevision']);
        }

        $this->service->delete(
            $this->admin(),
            (int) $created['template']['id'],
            ['revision' => 2]
        );
        $this->assertSame([], $this->service->list($this->admin())['items']);
        $row = $this->templateDb->table('token_templates')
            ->where('id', (int) $created['template']['id'])->get()->getRowArray();
        $this->assertNotNull($row['deleted_at']);
        $this->assertSame(3, (int) $row['revision']);
        $this->assertSame(1, (int) $row['updated_by_user_id']);
    }

    public function testReplacingUploadKeepsPreviousAssetAvailable(): void
    {
        $firstUpload = $this->upload('first.png');
        $created = $this->service->create(
            $this->admin(),
            array_diff_key($this->payload(), ['imageUrl' => true]),
            $firstUpload
        );
        $firstAssetId = (int) $created['template']['imageAssetId'];
        $firstAsset = $this->templateDb->table('token_template_assets')
            ->where('id', $firstAssetId)->get()->getRowArray();
        $firstPath = $this->assetDirectory . DIRECTORY_SEPARATOR . $firstAsset['storage_key'];

        $updated = $this->service->update(
            $this->admin(),
            (int) $created['template']['id'],
            ['revision' => 1],
            $this->upload('second.png')
        );

        $this->assertNotSame($firstAssetId, (int) $updated['template']['imageAssetId']);
        $this->assertSame(2, $this->templateDb->table('token_template_assets')->countAllResults());
        $this->assertFileExists($firstPath);
    }

    private function admin(): array
    {
        return ['user_id' => 1, 'role' => 'admin'];
    }

    private function payload(): array
    {
        return [
            'name' => 'Orc',
            'imageUrl' => 'https://assets.example.test/orc.webp',
            'widthCells' => 2,
            'heightCells' => 1.5,
            'movementRange' => 8,
            'resources' => [],
            'vision' => [],
        ];
    }

    private function upload(string $name): AdminTokenTemplateUploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'admin-token-template-upload-');
        file_put_contents(
            $path,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true)
        );
        return new AdminTokenTemplateUploadedFile($path, $name, 'image/png');
    }

    private function createSchema(): void
    {
        foreach ([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT, deleted_at TEXT)',
            'CREATE TABLE token_template_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, storage_key TEXT, original_name TEXT, mime_type TEXT, byte_size INTEGER, width INTEGER, height INTEGER, created_by_user_id INTEGER, created_at TEXT)',
            'CREATE TABLE token_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, image_url TEXT, image_asset_id INTEGER, width_cells REAL, height_cells REAL, rotation REAL, facing REAL, rotation_handle_enabled INTEGER, facing_handle_enabled INTEGER, rotation_follows_facing INTEGER, show_info_unselected INTEGER, resource_bar_position TEXT, elevation REAL, disposition TEXT, movement_range REAL, movement_reset_mode TEXT, bars_json TEXT, vision_json TEXT, revision INTEGER, created_by_user_id INTEGER, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
        ] as $statement) {
            $this->templateDb->query($statement);
        }
    }
}

final class AdminTokenTemplateUploadedFile extends UploadedFile
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
