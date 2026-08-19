<?php

use App\Models\ResourcePermissionModel;
use App\Services\Authorization\ResourcePermissionRevoker;
use App\Services\Authorization\ResourceType;
use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

final class ResourcePermissionRevokeStoreStub extends ResourcePermissionModel
{
    public $deletedId;

    public function __construct()
    {
    }

    public function where($key = null, $value = null, ?bool $escape = null)
    {
        return $this;
    }

    public function first()
    {
        return ['id' => 44, 'access_level' => 'owner'];
    }

    public function delete($id = null, bool $purge = false)
    {
        $this->deletedId = $id;
        return true;
    }
}

/** @internal */
final class ResourcePermissionShopIsolationTest extends CIUnitTestCase
{
    public function testCharacterRevokeAtomicallyDeletesMatchingShopClaim(): void
    {
        $builder = $this->claimBuilder();
        $builder->expects($this->exactly(3))->method('where')
            ->withConsecutive(
                ['campaign_id', 12],
                ['character_id', 5],
                ['user_id', 7]
            )->willReturnSelf();
        $builder->expects($this->once())->method('delete')->willReturn(true);

        $db = $this->connection();
        $db->expects($this->once())->method('transBegin')->willReturn(true);
        $db->expects($this->once())->method('tableExists')
            ->with('shop_owner_claims')->willReturn(true);
        $db->expects($this->once())->method('table')
            ->with('shop_owner_claims')->willReturn($builder);
        $db->expects($this->once())->method('transStatus')->willReturn(true);
        $db->expects($this->once())->method('transCommit')->willReturn(true);
        $db->expects($this->never())->method('transRollback');

        $permissions = new ResourcePermissionRevokeStoreStub();
        $result = (new ResourcePermissionRevoker($db, $permissions))->revoke(
            12,
            ResourceType::CHARACTER,
            5,
            7
        );

        $this->assertTrue($result['revoked']);
        $this->assertSame(7, $result['userId']);
        $this->assertSame(44, $permissions->deletedId);
    }

    public function testClaimDeleteFailureRollsBackPermissionRevoke(): void
    {
        $builder = $this->claimBuilder();
        $builder->method('where')->willReturnSelf();
        $builder->expects($this->once())->method('delete')->willReturn(false);

        $db = $this->connection();
        $db->expects($this->once())->method('transBegin')->willReturn(true);
        $db->expects($this->once())->method('tableExists')->willReturn(true);
        $db->expects($this->once())->method('table')->willReturn($builder);
        $db->expects($this->never())->method('transStatus');
        $db->expects($this->never())->method('transCommit');
        $db->expects($this->once())->method('transRollback')->willReturn(true);

        try {
            (new ResourcePermissionRevoker(
                $db,
                new ResourcePermissionRevokeStoreStub()
            ))->revoke(12, ResourceType::CHARACTER, 5, 7);
            $this->fail('A failed legacy claim delete must abort the revoke.');
        } catch (CampaignException $exception) {
            $this->assertSame('permission_write_failed', $exception->errorCode());
            $this->assertSame(500, $exception->status());
        }
    }

    private function claimBuilder(): BaseBuilder
    {
        return $this->getMockBuilder(BaseBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'delete'])
            ->getMock();
    }

    private function connection(): BaseConnection
    {
        return $this->getMockBuilder(BaseConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'transBegin',
                'transStatus',
                'transCommit',
                'transRollback',
                'tableExists',
                'table',
            ])
            ->getMockForAbstractClass();
    }
}
