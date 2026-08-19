<?php

use App\Services\Authorization\ResourceScopeService;
use App\Services\Authorization\ResourceType;
use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ResourceScopeServiceTest extends CIUnitTestCase
{
    public function testTypeWithoutBackingTableIsRejected(): void
    {
        $db = $this->getMockBuilder(BaseConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->expectException(CampaignException::class);
        $this->expectExceptionCode(0);
        try {
            (new ResourceScopeService($db))->resolve(ResourceType::SHARED, 5, 12);
        } catch (CampaignException $exception) {
            $this->assertSame('resource_type_unavailable', $exception->errorCode());
            $this->assertSame(409, $exception->status());
            throw $exception;
        }
    }

    public function testMissingResourceIdIsRejectedInsideCampaignScope(): void
    {
        $result = $this->getMockBuilder(BaseResult::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRowArray'])
            ->getMockForAbstractClass();
        $result->method('getRowArray')->willReturn(null);
        $builder = $this->getMockBuilder(BaseBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'get'])
            ->getMock();
        $builder->expects($this->exactly(2))->method('where')
            ->withConsecutive(['id', 999], ['campaign_id', 12])
            ->willReturnSelf();
        $builder->method('get')->willReturn($result);
        $db = $this->getMockBuilder(BaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['tableExists', 'table', 'fieldExists'])
            ->getMockForAbstractClass();
        $db->method('tableExists')->with('scenes')->willReturn(true);
        $db->method('table')->with('scenes')->willReturn($builder);
        $db->method('fieldExists')->willReturnCallback(
            static function (string $field, string $table): bool {
                return $table === 'scenes' && $field === 'campaign_id';
            }
        );

        try {
            (new ResourceScopeService($db))->resolve(ResourceType::SCENE, 999, 12);
            $this->fail('A missing resource id must be rejected.');
        } catch (CampaignException $exception) {
            $this->assertSame('resource_not_found', $exception->errorCode());
            $this->assertSame(404, $exception->status());
        }
    }
}
