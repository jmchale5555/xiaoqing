<?php

namespace Tests\Feature;

use Tests\Support\HttpClient;
use Tests\TestCase;

class TablesApiTest extends TestCase
{
    private HttpClient $staff;

    public static function setUpBeforeClass(): void
    {
        self::resetTestDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = new HttpClient(self::baseUrl());
        $this->signupClient($this->staff, 'tables-staff' . str_replace('.', '', uniqid('', true)) . '@example.com');
    }

    public function testTablesIndexReturnsArray(): void
    {
        $response = $this->staff->get('/api/tables');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['json']['tables'] ?? null);
    }

    public function testCreateShowUpdateDeleteTableFlow(): void
    {
        $token = $this->staff->csrfToken();
        $create = $this->staff->post('/api/tables/create', [
            'name' => 'Table A1',
            'seats' => 4,
            'is_active' => true,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $create['status']);
        $tableId = (int)($create['json']['table']['id'] ?? 0);
        $this->assertGreaterThan(0, $tableId);

        $show = $this->staff->get('/api/tables/show/' . $tableId);
        $this->assertSame(200, $show['status']);
        $this->assertSame('Table A1', $show['json']['table']['name'] ?? '');

        $updateToken = $this->staff->csrfToken();
        $update = $this->staff->post('/api/tables/update/' . $tableId, [
            'seats' => 6,
            'is_active' => false,
        ], ['X-CSRF-Token' => $updateToken]);

        $this->assertSame(200, $update['status']);
        $this->assertSame(6, (int)($update['json']['table']['seats'] ?? 0));
        $this->assertFalse((bool)($update['json']['table']['is_active'] ?? true));

        $deleteToken = $this->staff->csrfToken();
        $delete = $this->staff->post('/api/tables/delete/' . $tableId, [], ['X-CSRF-Token' => $deleteToken]);
        $this->assertSame(200, $delete['status']);

        $missing = $this->staff->get('/api/tables/show/' . $tableId);
        $this->assertSame(404, $missing['status']);
    }

    public function testCreateValidatesFields(): void
    {
        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/tables/create', [
            'name' => '',
            'seats' => 0,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('name', $response['json']['errors'] ?? []);
        $this->assertArrayHasKey('seats', $response['json']['errors'] ?? []);
    }

    public function testCreateRequiresAuthenticationAndCsrf(): void
    {
        $guest = new HttpClient(self::baseUrl());
        $withoutAuth = $guest->post('/api/tables/create', [
            'name' => 'Guest Table',
            'seats' => 2,
        ]);

        $this->assertSame(419, $withoutAuth['status']);

        $csrf = $guest->csrfToken();
        $withCsrfNoAuth = $guest->post('/api/tables/create', [
            'name' => 'Guest Table',
            'seats' => 2,
        ], ['X-CSRF-Token' => $csrf]);

        $this->assertSame(401, $withCsrfNoAuth['status']);
    }

    public function testTablesReorderPersistsOrder(): void
    {
        $firstId = $this->createTable('T1', 2);
        $secondId = $this->createTable('T2', 4);
        $thirdId = $this->createTable('T3', 6);

        $token = $this->staff->csrfToken();
        $reorder = $this->staff->post('/api/tables/reorder', [
            'ids' => [$thirdId, $firstId, $secondId],
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(200, $reorder['status']);

        $tables = $reorder['json']['tables'] ?? [];
        $indexById = [];
        foreach ($tables as $table)
        {
            $indexById[(int)($table['id'] ?? 0)] = (int)($table['display_order'] ?? -1);
        }

        $this->assertSame(0, $indexById[$thirdId] ?? -1);
        $this->assertSame(1, $indexById[$firstId] ?? -1);
        $this->assertSame(2, $indexById[$secondId] ?? -1);
    }

    public function testTablesReorderRejectsInvalidIds(): void
    {
        $firstId = $this->createTable('R1', 2);
        $secondId = $this->createTable('R2', 4);

        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/tables/reorder', [
            'ids' => [$firstId, $secondId, 999999],
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('ids', $response['json']['errors'] ?? []);
    }

    public function testTablesIndexSupportsIsActiveFilter(): void
    {
        $activeId = $this->createTable('Active Table', 4, true);
        $inactiveId = $this->createTable('Inactive Table', 4, false);

        $active = $this->staff->get('/api/tables?is_active=1');
        $inactive = $this->staff->get('/api/tables?is_active=0');

        $this->assertSame(200, $active['status']);
        $this->assertSame(200, $inactive['status']);

        $activeIds = array_map(fn ($table) => (int)($table['id'] ?? 0), $active['json']['tables'] ?? []);
        $inactiveIds = array_map(fn ($table) => (int)($table['id'] ?? 0), $inactive['json']['tables'] ?? []);

        $this->assertContains($activeId, $activeIds);
        $this->assertNotContains($inactiveId, $activeIds);
        $this->assertContains($inactiveId, $inactiveIds);
    }

    private function signupClient(HttpClient $client, string $email): void
    {
        $token = $client->csrfToken();
        $response = $client->post('/api/auth/signup', [
            'name' => 'Table Staff',
            'email' => $email,
            'password' => 'secret123',
            'confirm' => 'secret123',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $response['status']);
    }

    private function createTable(string $name, int $seats, bool $active = true): int
    {
        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/tables/create', [
            'name' => $name,
            'seats' => $seats,
            'is_active' => $active,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $response['status']);

        return (int)($response['json']['table']['id'] ?? 0);
    }
}
