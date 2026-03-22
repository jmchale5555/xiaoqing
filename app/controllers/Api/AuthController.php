<?php

namespace Controller\Api;

use Core\ApiController;
use Core\Request;
use Core\Session;
use Model\User;
use Throwable;

defined('ROOTPATH') or exit('Access Denied');

class AuthController extends ApiController
{
    public function index(): void
    {
        $this->ok([
            'endpoints' => [
                'POST /api/auth/login',
                'POST /api/auth/signup',
                'POST /api/auth/logout',
                'GET /api/auth/me',
                'GET /api/auth/csrf',
            ],
        ]);
    }

    public function csrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $this->ok(['csrfToken' => $this->issueCsrfToken()]);
    }

    public function me(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $session = new Session();
        $user = $session->user();

        $this->ok(['user' => $user ? $this->sanitizeUser($user) : null]);
    }

    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        {
            $this->methodNotAllowed(['POST']);
            return;
        }

        $request = new Request();
        $payload = $request->json();

        if (empty($payload))
        {
            $payload = $request->post();
        }

        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');

        if (!$this->verifyCsrf($payload))
        {
            return;
        }

        if ($email === '' || $password === '')
        {
            $errors = [];

            if ($email === '')
            {
                $errors['email'] = 'Email is required';
            }

            if ($password === '')
            {
                $errors['password'] = 'Password is required';
            }

            $this->validationError($errors, 'Email and password are required');
            return;
        }

        $user = new User();
        try
        {
            $row = $user->first(['email' => $email]);
        }
        catch (Throwable $e)
        {
            $this->error('Authentication service unavailable', 500);
            return;
        }

        if (!$row || !password_verify($password, $row->password))
        {
            $this->unauthenticated('Wrong email or password');
            return;
        }

        $session = new Session();
        $session->auth($row);

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_regenerate_id(true);
        }

        $this->ok(['user' => $this->sanitizeUser($row)]);
    }

    public function signup(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        {
            $this->methodNotAllowed(['POST']);
            return;
        }

        $request = new Request();
        $payload = $request->json();

        if (empty($payload))
        {
            $payload = $request->post();
        }

        if (!$this->verifyCsrf($payload))
        {
            return;
        }

        $user = new User();

        if (!$user->validate($payload))
        {
            $this->validationError($user->errors);
            return;
        }

        try
        {
            $existing = $user->first(['email' => (string)($payload['email'] ?? '')]);
        }
        catch (Throwable $e)
        {
            $this->error('Authentication service unavailable', 500);
            return;
        }

        if ($existing)
        {
            $this->validationError(['email' => 'Email already exists']);
            return;
        }

        $payload['password'] = password_hash((string)$payload['password'], PASSWORD_BCRYPT);
        unset($payload['confirm']);

        try
        {
            $user->insert($payload);
            $newUser = $user->first(['email' => $payload['email']]);
        }
        catch (Throwable $e)
        {
            $this->error('Authentication service unavailable', 500);
            return;
        }

        if (!$newUser)
        {
            $this->error('Unable to create user', 500);
            return;
        }

        $session = new Session();
        $session->auth($newUser);

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_regenerate_id(true);
        }

        $this->ok(['user' => $this->sanitizeUser($newUser)], 201);
    }

    public function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        {
            $this->methodNotAllowed(['POST']);
            return;
        }

        $request = new Request();
        $payload = $request->json();
        if (empty($payload))
        {
            $payload = $request->post();
        }

        if (!$this->verifyCsrf($payload))
        {
            return;
        }

        $session = new Session();
        $session->logout();

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_regenerate_id(true);
        }

        $this->ok(['ok' => true]);
    }

    private function issueCsrfToken(): string
    {
        $session = new Session();
        $token = (string)$session->get('csrf_token', '');

        if ($token === '')
        {
            $token = bin2hex(random_bytes(32));
            $session->set('csrf_token', $token);
        }

        return $token;
    }

    private function verifyCsrf(array $payload = []): bool
    {
        $session = new Session();
        $expected = (string)$session->get('csrf_token', '');

        if ($expected === '')
        {
            $expected = $this->issueCsrfToken();
        }

        $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($provided === '')
        {
            $provided = (string)($payload['csrfToken'] ?? '');
        }

        if ($provided === '' || !hash_equals($expected, $provided))
        {
            $this->error('Invalid CSRF token', 419);
            return false;
        }

        return true;
    }

    private function sanitizeUser(mixed $user): array
    {
        return [
            'id' => $user->id ?? null,
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
        ];
    }
}
