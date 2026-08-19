<?php

use App\Services\Shop\AuthContextService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ShopAuthContextScopeTest extends CIUnitTestCase
{
    public function testShopDevelopmentHeadersCannotEscalateAnotherApiRoute(): void
    {
        $previousEnvironment = getenv('CI_ENVIRONMENT');
        $previousFlag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        putenv('CI_ENVIRONMENT=development');
        putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS=true');

        try {
            $request = $this->createMock(RequestInterface::class);
            $request->method('getServer')->willReturnCallback(
                static function (string $name) {
                    return $name === 'REQUEST_URI' ? '/api/campaigns/1/scenes' : null;
                }
            );
            $request->method('getHeaderLine')->willReturnCallback(
                static function (string $name): string {
                    return $name === 'X-Shop-Access-Mode' ? 'gm' : '';
                }
            );

            $result = (new AuthContextService())->resolveFromRequest($request);

            $this->assertNull($result['user_id']);
            $this->assertNull($result['role']);
            $this->assertFalse($result['character_view']);
            $this->assertArrayNotHasKey('development_access', $result);
        } finally {
            $previousEnvironment === false
                ? putenv('CI_ENVIRONMENT')
                : putenv('CI_ENVIRONMENT='.$previousEnvironment);
            $previousFlag === false
                ? putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS')
                : putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS='.$previousFlag);
        }
    }
}
