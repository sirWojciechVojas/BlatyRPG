<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use Config\SubscriptionPlans;
use CodeIgniter\HTTP\ResponseInterface;

final class SubscriptionPlanController extends BaseController
{
    private $catalog;

    public function __construct(?SubscriptionPlans $catalog = null)
    {
        $this->catalog = $catalog ?: config('SubscriptionPlans');
    }

    public function index(): ResponseInterface
    {
        return $this->response
            ->setHeader('Cache-Control', 'public, max-age=300')
            ->setStatusCode(200)
            ->setJSON(['plans' => $this->catalog->plans]);
    }
}
