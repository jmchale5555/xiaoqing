<?php

namespace Tests\Feature;

use Tests\Support\HttpClient;
use Tests\TestCase;

class ApiRoutingTest extends TestCase
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

    public function testApiResourceDefaultsMethodToIndex(): void
    {
        $response = $this->client->get('/api/health');

        $this->assertSame(200, $response['status']);
        $this->assertTrue((bool)($response['json']['ok'] ?? false));
    }

    public function testUnknownApiResourceReturns404(): void
    {
        $response = $this->client->get('/api/not-real');

        $this->assertSame(404, $response['status']);
        $this->assertSame('API controller not found', $response['json']['message'] ?? null);
    }

    public function testUnknownApiMethodReturns404(): void
    {
        $response = $this->client->get('/api/health/notARealMethod');

        $this->assertSame(404, $response['status']);
        $this->assertSame('API endpoint not found', $response['json']['message'] ?? null);
    }
}
