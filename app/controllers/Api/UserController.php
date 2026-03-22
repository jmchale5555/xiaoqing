<?php

namespace Controller\Api;

use Core\ApiController;
use Core\Session;

defined('ROOTPATH') or exit('Access Denied');

class UserController extends ApiController
{
    public function index(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $session = new Session();
        $user = $session->user();

        if (!$user)
        {
            $this->unauthenticated();
            return;
        }

        $this->ok([
            'user' => [
                'id' => $user->id ?? null,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
            ],
        ]);
    }
}
