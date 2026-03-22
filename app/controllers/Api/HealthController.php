<?php

namespace Controller\Api;

use Core\ApiController;

defined('ROOTPATH') or exit('Access Denied');

class HealthController extends ApiController
{
    public function index(): void
    {
        $this->ok([
            'ok' => true,
            'time' => date(DATE_ATOM),
        ]);
    }
}
