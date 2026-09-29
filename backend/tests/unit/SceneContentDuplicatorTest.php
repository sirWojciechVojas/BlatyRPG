<?php

use App\Services\Scene\SceneContentDuplicator;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class SceneContentDuplicatorTest extends CIUnitTestCase
{
    public function testDoesNotCopyTokenSynchronizationLinks(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Scene/SceneContentDuplicator.php'
        );

        $this->assertStringNotContainsString(
            "'scene_token_sync_links'",
            $source
        );
    }

    public function testCopiesCurrentElementStateAndResetsRecordMetadata(): void
    {
        $result = $this->getMockBuilder(BaseResult::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResultArray'])
            ->getMockForAbstractClass();
        $result->expects($this->once())->method('getResultArray')->willReturn([[
            'id' => 91,
            'campaign_id' => 7,
            'scene_id' => 4,
            'name' => 'Guard',
            'x' => '123.500',
            'y' => '456.250',
            'rotation' => '90.000',
            'bars_json' => '{"bars":[{"value":4}]}',
            'revision' => 8,
            'created_at' => '2026-01-01 10:00:00',
            'updated_at' => '2026-01-02 10:00:00',
            'deleted_at' => null,
        ]]);

        $reader = $this->builder(['where', 'get']);
        $reader->expects($this->exactly(3))->method('where')
            ->withConsecutive(
                ['campaign_id', 7],
                ['scene_id', 4],
                ['deleted_at', null]
            )->willReturnSelf();
        $reader->expects($this->once())->method('get')->willReturn($result);

        $writer = $this->builder(['insertBatch']);
        $writer->expects($this->once())->method('insertBatch')
            ->with($this->callback(static function (array $rows): bool {
                $copy = $rows[0] ?? [];
                return !array_key_exists('id', $copy)
                    && $copy['campaign_id'] === 7
                    && $copy['scene_id'] === 12
                    && $copy['x'] === '123.500'
                    && $copy['y'] === '456.250'
                    && $copy['rotation'] === '90.000'
                    && $copy['bars_json'] === '{"bars":[{"value":4}]}'
                    && $copy['revision'] === 1
                    && $copy['created_at'] !== '2026-01-01 10:00:00'
                    && $copy['created_at'] === $copy['updated_at']
                    && $copy['deleted_at'] === null;
            }))
            ->willReturn(1);

        $db = $this->connection();
        $db->expects($this->exactly(6))->method('tableExists')
            ->willReturnCallback(static fn (string $table): bool => $table === 'scene_tokens');
        $db->expects($this->once())->method('fieldExists')
            ->with('deleted_at', 'scene_tokens')->willReturn(true);
        $db->expects($this->exactly(2))->method('table')
            ->with('scene_tokens')->willReturnOnConsecutiveCalls($reader, $writer);

        (new SceneContentDuplicator($db))->duplicate(7, 4, 12);
    }

    private function builder(array $methods): BaseBuilder
    {
        return $this->getMockBuilder(BaseBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    private function connection(): BaseConnection
    {
        return $this->getMockBuilder(BaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tableExists', 'fieldExists', 'table'])
            ->getMockForAbstractClass();
    }
}
