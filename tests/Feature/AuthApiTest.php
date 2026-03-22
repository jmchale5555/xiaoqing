<?php

namespace Tests\Feature;

use Tests\Support\HttpClient;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    private HttpClient $client;

    public static function setUpBeforeClass(): void
    {
        self::resetTestDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new HttpClient(self::baseUrl());
    }

    public function testCsrfEndpointReturnsToken(): void
    {
        $response = $this->client->get('/api/auth/csrf');

        $this->assertSame(200, $response['status']);
        $this->assertIsString($response['json']['csrfToken'] ?? null);
        $this->assertNotEmpty($response['json']['csrfToken'] ?? '');
    }

    public function testSignupRequiresCsrfToken(): void
    {
        $response = $this->client->post('/api/auth/signup', [
            'name' => 'No Csrf',
            'email' => 'nocsrf@example.com',
            'password' => 'secret123',
            'confirm' => 'secret123',
        ]);

        $this->assertSame(419, $response['status']);
        $this->assertSame('Invalid CSRF token', $response['json']['message'] ?? null);
    }

    public function testSignupAndMeFlow(): void
    {
        $token = $this->client->csrfToken();
        $email = 'user' . time() . '@example.com';

        $signup = $this->client->post('/api/auth/signup', [
            'name' => 'Auth User',
            'email' => $email,
            'password' => 'secret123',
            'confirm' => 'secret123',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $signup['status']);
        $this->assertSame($email, $signup['json']['user']['email'] ?? null);

        $me = $this->client->get('/api/auth/me');
        $this->assertSame(200, $me['status']);
        $this->assertSame($email, $me['json']['user']['email'] ?? null);
    }

    public function testLoginWrongPasswordReturns401(): void
    {
        $token = $this->client->csrfToken();
        $email = 'login' . time() . '@example.com';

        $this->client->post('/api/auth/signup', [
            'name' => 'Login User',
            'email' => $email,
            'password' => 'secret123',
            'confirm' => 'secret123',
        ], ['X-CSRF-Token' => $token]);

        $token2 = $this->client->csrfToken();
        $login = $this->client->post('/api/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ], ['X-CSRF-Token' => $token2]);

        $this->assertSame(401, $login['status']);
        $this->assertSame('Wrong email or password', $login['json']['message'] ?? null);
    }
}
