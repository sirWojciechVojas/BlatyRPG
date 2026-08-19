<?php

use App\Services\Shop\AuthContextService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopAuthContextServiceTest extends CIUnitTestCase
{
    public function testResolveFromAuthorizationHeaderNeverGrantsDevAdminContext(): void
    {
        $previousEnvironment = getenv('CI_ENVIRONMENT');
        $previousFlag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        putenv('CI_ENVIRONMENT=development');
        putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS=true');

        try {
            $service = new AuthContextService();
            $result = $service->resolveFromAuthorizationHeader(null);

            $this->assertNull($result['user_id']);
            $this->assertNull($result['role']);
            $this->assertNull($result['token']);
            $this->assertTrue($result['anonymous']);
        } finally {
            $previousEnvironment === false
                ? putenv('CI_ENVIRONMENT')
                : putenv('CI_ENVIRONMENT='.$previousEnvironment);
            $previousFlag === false
                ? putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS')
                : putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS='.$previousFlag);
        }
    }

    public function testAnonymousShopAccessRemainsDisabledInProduction(): void
    {
        $previousEnvironment = getenv('CI_ENVIRONMENT');
        $previousFlag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        putenv('CI_ENVIRONMENT=production');
        putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS=true');

        try {
            $service = new AuthContextService();
            $result = $service->resolveFromAuthorizationHeader(null);

            $this->assertNull($result['user_id']);
            $this->assertNull($result['role']);
            $this->assertNull($result['token']);
        } finally {
            $previousEnvironment === false
                ? putenv('CI_ENVIRONMENT')
                : putenv('CI_ENVIRONMENT='.$previousEnvironment);
            $previousFlag === false
                ? putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS')
                : putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS='.$previousFlag);
        }
    }

    public function testResolveFromAuthorizationHeaderDoesNotRetainRawToken(): void
    {
        $previous = getenv('JWT_SECRET');
        putenv('JWT_SECRET');

        $service = new AuthContextService();
        $result = $service->resolveFromAuthorizationHeader('Bearer abc.def.ghi');

        $this->assertNull($result['user_id']);
        $this->assertNull($result['role']);
        $this->assertNull($result['token']);

        if ($previous !== false) {
            putenv('JWT_SECRET='.$previous);
        }
    }

    public function testDevelopmentGmCanRequestCharacterShoppingView(): void
    {
        $previousEnvironment = getenv('CI_ENVIRONMENT');
        $previousFlag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        putenv('CI_ENVIRONMENT=development');
        putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS=true');

        try {
            $request = $this->createMock(RequestInterface::class);
            $request->method('getServer')->willReturnCallback(
                static function (string $name) {
                    return $name === 'REQUEST_URI'
                        ? '/api/shop/campaigns/1/bootstrap'
                        : null;
                }
            );
            $request->method('getHeaderLine')->willReturnCallback(
                static function (string $name): string {
                    return [
                        'X-Shop-Access-Mode' => 'gm',
                        'X-Shop-View-Mode' => 'character',
                        'X-Shop-Owner-Code' => 'CHAR_3',
                        'X-Shop-Character-Id' => '3',
                    ][$name] ?? '';
                }
            );

            $result = (new AuthContextService())->resolveFromRequest($request);

            $this->assertSame('gm', $result['role']);
            $this->assertTrue($result['character_view']);
            $this->assertSame(['CHAR_3'], $result['selected_owner_codes']);
            $this->assertSame(3, $result['character_id']);
        } finally {
            $previousEnvironment === false
                ? putenv('CI_ENVIRONMENT')
                : putenv('CI_ENVIRONMENT='.$previousEnvironment);
            $previousFlag === false
                ? putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS')
                : putenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS='.$previousFlag);
        }
    }

    public function testIsGmOrAdminRecognizesSupportedRoles(): void
    {
        $service = new AuthContextService();

        $this->assertTrue($service->isGmOrAdmin(['role' => 'gm']));
        $this->assertTrue($service->isGmOrAdmin(['role' => 'admin']));
        $this->assertFalse($service->isGmOrAdmin(['role' => 'user']));
    }
}
