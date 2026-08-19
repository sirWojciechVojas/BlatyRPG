<?php

use App\Services\Authorization\ResourcePermissionService;
use App\Services\Scene\SceneResourceAccessService;
use CodeIgniter\Test\CIUnitTestCase;

final class ScenePermissionLevelStub extends ResourcePermissionService
{
    private $level;

    public function __construct(string $level)
    {
        $this->level = $level;
    }

    public function levelFor(
        array $auth,
        int $campaignId,
        string $resourceType,
        int $resourceId
    ): string {
        return $this->level;
    }
}

/** @internal */
final class SceneResourceAccessServiceTest extends CIUnitTestCase
{
    public function testObserverGrantCanReadHiddenSceneButCannotManageIt(): void
    {
        $service = new SceneResourceAccessService(new ScenePermissionLevelStub('observer'));
        $auth = ['user_id' => 4, 'role' => 'player'];
        $capabilities = ['canManage' => false, 'canViewHidden' => false];

        $this->assertTrue($service->canView(
            $auth,
            2,
            ['id' => 9, 'is_visible' => 0],
            $capabilities
        ));
        $this->assertFalse($service->canManage($auth, 2, 9, $capabilities));
    }

    public function testOwnerGrantCanManageSpecificScene(): void
    {
        $service = new SceneResourceAccessService(new ScenePermissionLevelStub('owner'));

        $this->assertTrue($service->canManage(
            ['user_id' => 4, 'role' => 'player'],
            2,
            9,
            ['canManage' => false, 'canViewHidden' => false]
        ));
    }
}
