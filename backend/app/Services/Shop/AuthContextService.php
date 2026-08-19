<?php

namespace App\Services\Shop;

use App\Services\Auth\AuthContextService as BaseAuthContextService;
use CodeIgniter\HTTP\RequestInterface;

/** Shop compatibility adapter for development-only character selectors. */
class AuthContextService extends BaseAuthContextService
{

    public function resolveFromRequest(RequestInterface $request): array
    {
        $auth = parent::resolveFromRequest($request);
        $auth['character_view'] = false;
        if (!$this->isShopRequest($request)) {
            return $auth;
        }

        $auth['character_view'] = strtolower(trim(
            $request->getHeaderLine('X-Shop-View-Mode')
        )) === 'character';
        if (!$this->isDevelopmentSelectorEnabled()) {
            return $auth;
        }

        $mode = strtolower(trim($request->getHeaderLine('X-Shop-Access-Mode')));
        if (!in_array($mode, ['gm', 'player'], true)) {
            return $auth;
        }

        $ownerCode = strtoupper(trim($request->getHeaderLine('X-Shop-Owner-Code')));
        $characterId = filter_var(
            $request->getHeaderLine('X-Shop-Character-Id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($mode === 'player' && !preg_match('/^[A-Z0-9_-]{1,64}$/', $ownerCode)) {
            return $auth;
        }

        return [
            'user_id' => null,
            'role' => $mode === 'gm' ? 'gm' : 'user',
            'token' => $auth['token'] ?? null,
            'anonymous' => true,
            'development_access' => true,
            'access_mode' => $mode,
            'character_view' => !empty($auth['character_view']),
            'selected_owner_codes' => $ownerCode ? [$ownerCode] : [],
            'character_id' => $characterId !== false ? (int) $characterId : null,
        ];
    }

    public function isDevelopmentSelectorEnabled(): bool
    {
        if (strtolower((string) getenv('CI_ENVIRONMENT')) === 'production') {
            return false;
        }

        $flag = getenv('SHOP_ALLOW_ANONYMOUS_SHOP_ACCESS');
        if ($flag !== false && $flag !== '') {
            return in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private function isShopRequest(RequestInterface $request): bool
    {
        $paths = [
            (string) $request->getServer('REQUEST_URI'),
            (string) $request->getServer('PATH_INFO'),
        ];

        try {
            $paths[] = $request->getUri()->getPath();
        } catch (\Throwable $exception) {
            // Some CLI/unit-test requests do not expose a URI object.
        }

        foreach ($paths as $path) {
            $path = trim((string) parse_url($path, PHP_URL_PATH), '/');
            $path = preg_replace('#^index\.php/#', '', $path) ?: $path;
            if (preg_match('#^api/shop(?:/|$)#', $path)) {
                return true;
            }
        }

        return false;
    }
}
