<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class StatusController extends BaseController
{
    public function app(): ResponseInterface
    {
        return $this->response->setStatusCode(200)->setJSON([
            'name'      => 'BlatyRPG API',
            'type'      => 'api-only',
            'status'    => 'ok',
            'framework' => 'CodeIgniter 4.4.8',
            'message'   => 'Backend działa poprawnie.',
        ]);
    }

    public function index(): ResponseInterface
    {
        return $this->response->setStatusCode(200)->setJSON([
            'name'   => 'BlatyRPG API',
            'type'   => 'api-only',
            'status' => 'ok',
        ]);
    }

    public function health(): ResponseInterface
    {
        return $this->response->setStatusCode(200)->setJSON([
            'status'    => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }
}