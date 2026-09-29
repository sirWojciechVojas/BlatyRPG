<?php

namespace App\Services\Shop;

use App\Services\Auth\AuthSessionService;
use App\Services\Auth\AuthContextService as BaseAuthContextService;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\HTTP\RequestInterface;

/** Shop compatibility adapter for development-only character selectors. */
class AuthContextService extends BaseAuthContextService
{
    private $campaignGuard;

    public function __construct(
        ?AuthSessionService $sessions = null,
        ?CampaignGuardService $campaignGuard = null
    ) {
        parent::__construct($sessions);
        $this->campaignGuard = $campaignGuard ?: new CampaignGuardService();
    }

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
        if ($this->isDevelopmentSelectorEnabled()) {
            $development = $this->developmentContext($request, $auth);
            if ($development !== null) {
                return $development;
            }
        }
        return $this->campaignContext($request, $auth);
    }

    private function developmentContext(RequestInterface $request, array $auth): ?array
    {
        $mode = strtolower(trim($request->getHeaderLine('X-Shop-Access-Mode')));
        if (!in_array($mode, ['gm', 'player'], true)) {
            return null;
        }
        $ownerCode = strtoupper(trim($request->getHeaderLine('X-Shop-Owner-Code')));
        if ($mode === 'player' && !preg_match('/^[A-Z0-9_-]{1,64}$/', $ownerCode)) {
            return null;
        }
        $characterId = filter_var(
            $request->getHeaderLine('X-Shop-Character-Id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        return [
            'user_id' => null,
            'role' => 'user',
            'campaign_role' => $mode,
            'is_campaign_manager' => $mode === 'gm',
            'token' => $auth['token'] ?? null,
            'anonymous' => true,
            'development_access' => true,
            'access_mode' => $mode,
            'character_view' => !empty($auth['character_view']),
            'selected_owner_codes' => $ownerCode ? [$ownerCode] : [],
            'character_id' => $characterId !== false ? (int) $characterId : null,
        ];
    }

    private function campaignContext(RequestInterface $request, array $auth): array
    {
        $campaignId = $this->campaignId($request);
        if ($campaignId === null || !empty($auth['anonymous'])) {
            return $auth;
        }
        try {
            $context = $this->campaignGuard->context($auth, $campaignId);
        } catch (CampaignException $exception) {
            return $auth + ['campaign_authorized' => false];
        }
        return $auth + [
            'campaign_authorized' => true,
            'global_role' => $context['globalRole'],
            'campaign_role' => $context['campaignRole'],
            'is_admin' => $context['isAdmin'],
            'is_campaign_manager' => !empty($context['capabilities']['canManage']),
        ];
    }

    private function campaignId(RequestInterface $request): ?int
    {
        $path = (string) $request->getServer('REQUEST_URI');
        if ($path === '') {
            $path = $request->getUri()->getPath();
        }
        return preg_match('#/api/shop/campaigns/([1-9][0-9]*)(?:/|$)#', $path, $match)
            ? (int) $match[1] : null;
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
