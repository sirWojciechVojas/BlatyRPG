<?php

namespace App\Filters;

use App\Services\Shop\AuthContextService;
use App\Services\Shop\ShopAuthorizationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ShopCampaignAccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim($request->getUri()->getPath(), '/');
        if (!preg_match('#(?:^|/)shop/campaigns/(\d+)(?:/|$)#', $path, $matches)) {
            return null;
        }

        if (preg_match('#/access/options$#', $path)) {
            return null;
        }

        $auth = (new AuthContextService())->resolveFromRequest($request);
        $access = (new ShopAuthorizationService())->assertCampaignAccess($auth, (int) $matches[1]);
        if ($access['ok']) {
            return null;
        }

        return Services::response()
            ->setStatusCode((int) ($access['status'] ?? 403))
            ->setJSON([
                'code' => $access['code'] ?? 'forbidden',
                'message' => $access['message'] ?? 'Forbidden.',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
