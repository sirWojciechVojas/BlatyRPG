<?php

use App\Models\CampaignModel;
use App\Models\ShopOwnerClaimModel;
use App\Services\Shop\ShopAuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;

final class ShopOwnerClaimBatchStub extends ShopOwnerClaimModel
{
    private $rows;
    public $findAllCalls = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function __call($name, $arguments)
    {
        return $this;
    }

    public function findAll(int $limit = 0, int $offset = 0)
    {
        $this->findAllCalls++;
        return $this->rows;
    }
}

/** @internal */
final class ShopCampaignAccessMapTest extends CIUnitTestCase
{
    public function testPlayerClaimsAreResolvedInOneBatch(): void
    {
        $claims = new ShopOwnerClaimBatchStub([
            ['campaign_id' => 2],
            ['campaign_id' => 3],
        ]);
        $campaigns = [
            ['id' => 1, 'game_master_id' => 10],
            ['id' => 2, 'game_master_id' => 11],
            ['id' => 3, 'game_master_id' => 12],
        ];
        $service = new ShopAuthorizationService(
            null,
            $claims,
            $this->campaignModel()
        );

        $result = $service->campaignAccessMap(
            ['user_id' => 20, 'role' => 'user'],
            $campaigns
        );

        $this->assertSame([1 => false, 2 => true, 3 => true], $result);
        $this->assertSame(1, $claims->findAllCalls);
    }

    public function testGmAndAdminMapsFollowExistingAuthorizationRules(): void
    {
        $claims = new ShopOwnerClaimBatchStub([]);
        $campaigns = [
            ['id' => 1, 'game_master_id' => 10],
            ['id' => 2, 'game_master_id' => 11],
        ];
        $service = new ShopAuthorizationService(
            null,
            $claims,
            $this->campaignModel()
        );

        $this->assertSame(
            [1 => true, 2 => false],
            $service->campaignAccessMap(['user_id' => 10, 'role' => 'gm'], $campaigns)
        );
        $this->assertSame(
            [1 => true, 2 => true],
            $service->campaignAccessMap(['user_id' => 99, 'role' => 'admin'], $campaigns)
        );
        $this->assertSame(0, $claims->findAllCalls);
    }

    private function campaignModel(): CampaignModel
    {
        return $this->getMockBuilder(CampaignModel::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
