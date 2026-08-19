<?php

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\UserModel;
use App\Services\Campaign\CampaignAccessPolicy;
use App\Services\Campaign\CampaignDirectoryService;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignPayloadValidator;
use App\Services\Shop\ShopAuthorizationService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

final class CampaignDirectoryUserStub extends UserModel
{
    private $row;

    public function __construct(?array $row)
    {
        $this->row = $row;
    }

    public function __call($name, $arguments)
    {
        return $this;
    }

    public function first()
    {
        return $this->row;
    }
}

/** @internal */
final class CampaignDirectoryAuthorizationTest extends CIUnitTestCase
{
    public function testCurrentDatabaseRoleOverridesStaleAdminJwtRole(): void
    {
        $service = $this->service([
            'id' => 7,
            'role' => 'user',
            'deleted_at' => null,
        ]);

        try {
            $service->create([
                'user_id' => 7,
                'role' => 'admin',
                'anonymous' => false,
            ], ['name' => 'Must not be created']);
            $this->fail('A demoted administrator must not create campaigns.');
        } catch (CampaignException $exception) {
            $this->assertSame(403, $exception->status());
            $this->assertSame('forbidden', $exception->errorCode());
        }
    }

    public function testDeletedUserIsRejectedEvenWithValidJwtClaims(): void
    {
        $service = $this->service(null);

        try {
            $service->listForUser([
                'user_id' => 7,
                'role' => 'admin',
                'anonymous' => false,
            ]);
            $this->fail('A deleted user must not retain campaign access.');
        } catch (CampaignException $exception) {
            $this->assertSame(401, $exception->status());
            $this->assertSame('unauthorized', $exception->errorCode());
        }
    }

    private function service(?array $user): CampaignDirectoryService
    {
        $db = $this->getMockBuilder(BaseConnection::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $campaigns = $this->getMockBuilder(CampaignModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $members = $this->getMockBuilder(CampaignMemberModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $shop = $this->getMockBuilder(ShopAuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new CampaignDirectoryService(
            $db,
            $campaigns,
            $members,
            new CampaignAccessPolicy(),
            new CampaignPayloadValidator(),
            $shop,
            new CampaignDirectoryUserStub($user)
        );
    }
}
