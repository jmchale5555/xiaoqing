<?php

namespace Tests\Feature;

use Tests\Support\HttpClient;
use Tests\TestCase;

class MenuApiTest extends TestCase
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
        $this->signupClient($this->staff, 'menu-staff' . str_replace('.', '', uniqid('', true)) . '@example.com');
    }

    public function testMenuIndexReturnsArray(): void
    {
        $response = $this->staff->get('/api/menu');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['json']['items'] ?? null);
    }

    public function testCreateShowUpdateDeleteMenuItemFlow(): void
    {
        $token = $this->staff->csrfToken();
        $create = $this->staff->post('/api/menu/create', [
            'name' => '辣子鸡 (Spicy chicken)',
            'description' => '辣子鸡',
            'price_pence' => 1299,
            'category' => '肉类 (Meat Dishes)',
            'is_available' => true,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $create['status']);
        $itemId = (int)($create['json']['item']['id'] ?? 0);
        $this->assertGreaterThan(0, $itemId);
        $this->assertSame(1299, (int)($create['json']['item']['price_pence'] ?? 0));

        $show = $this->staff->get('/api/menu/show/' . $itemId);
        $this->assertSame(200, $show['status']);
        $this->assertSame('辣子鸡 (Spicy chicken)', $show['json']['item']['name'] ?? '');

        $updateToken = $this->staff->csrfToken();
        $update = $this->staff->post('/api/menu/update/' . $itemId, [
            'price_pence' => 1399,
            'is_available' => false,
        ], ['X-CSRF-Token' => $updateToken]);

        $this->assertSame(200, $update['status']);
        $this->assertSame(1399, (int)($update['json']['item']['price_pence'] ?? 0));
        $this->assertFalse((bool)($update['json']['item']['is_available'] ?? true));

        $deleteToken = $this->staff->csrfToken();
        $delete = $this->staff->post('/api/menu/delete/' . $itemId, [], ['X-CSRF-Token' => $deleteToken]);

        $this->assertSame(200, $delete['status']);

        $missing = $this->staff->get('/api/menu/show/' . $itemId);
        $this->assertSame(404, $missing['status']);
    }

    public function testCreateValidatesRequiredFields(): void
    {
        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/menu/create', [
            'name' => '',
            'price_pence' => -1,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('name', $response['json']['errors'] ?? []);
        $this->assertArrayHasKey('price_pence', $response['json']['errors'] ?? []);
    }

    public function testCreateRequiresAuthenticationAndCsrf(): void
    {
        $guest = new HttpClient(self::baseUrl());

        $withoutAuth = $guest->post('/api/menu/create', [
            'name' => 'Unauthorized item',
            'price_pence' => 1000,
        ]);

        $this->assertSame(419, $withoutAuth['status']);

        $csrf = $guest->csrfToken();
        $withCsrfNoAuth = $guest->post('/api/menu/create', [
            'name' => 'Unauthorized item',
            'price_pence' => 1000,
        ], ['X-CSRF-Token' => $csrf]);

        $this->assertSame(401, $withCsrfNoAuth['status']);
    }

    public function testMenuReorderPersistsOrder(): void
    {
        $firstId = $this->createMenuItem('A item', 1000);
        $secondId = $this->createMenuItem('B item', 1100);
        $thirdId = $this->createMenuItem('C item', 1200);

        $token = $this->staff->csrfToken();
        $reorder = $this->staff->post('/api/menu/reorder', [
            'ids' => [$thirdId, $firstId, $secondId],
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(200, $reorder['status']);
        $items = $reorder['json']['items'] ?? [];
        $this->assertIsArray($items);

        $indexById = [];
        foreach ($items as $item)
        {
            $indexById[(int)($item['id'] ?? 0)] = (int)($item['display_order'] ?? -1);
        }

        $this->assertSame(0, $indexById[$thirdId] ?? -1);
        $this->assertSame(1, $indexById[$firstId] ?? -1);
        $this->assertSame(2, $indexById[$secondId] ?? -1);
    }

    public function testMenuReorderRejectsInvalidIds(): void
    {
        $firstId = $this->createMenuItem('One', 900);
        $secondId = $this->createMenuItem('Two', 950);

        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/menu/reorder', [
            'ids' => [$firstId, $secondId, 999999],
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(422, $response['status']);
        $this->assertArrayHasKey('ids', $response['json']['errors'] ?? []);
    }

    public function testMenuImageUploadSuccessAndValidationFailure(): void
    {
        $pngPath = $this->createTempPng();
        $txtPath = $this->createTempTextFile();

        try
        {
            $token = $this->staff->csrfToken();
            $upload = $this->staff->postMultipart('/api/uploads/menu_image', [], [
                'image' => $pngPath,
            ], ['X-CSRF-Token' => $token]);

            $this->assertSame(201, $upload['status']);
            $path = (string)($upload['json']['image_path'] ?? '');
            $this->assertStringStartsWith('/uploads/menu/', $path);

            $invalidToken = $this->staff->csrfToken();
            $invalid = $this->staff->postMultipart('/api/uploads/menu_image', [], [
                'image' => $txtPath,
            ], ['X-CSRF-Token' => $invalidToken]);

            $this->assertSame(422, $invalid['status']);
            $this->assertArrayHasKey('image', $invalid['json']['errors'] ?? []);
        }
        finally
        {
            @unlink($pngPath);
            @unlink($txtPath);
        }
    }

    private function signupClient(HttpClient $client, string $email): void
    {
        $token = $client->csrfToken();
        $response = $client->post('/api/auth/signup', [
            'name' => 'Menu Staff',
            'email' => $email,
            'password' => 'secret123',
            'confirm' => 'secret123',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $response['status']);
    }

    private function createMenuItem(string $name, int $pricePence): int
    {
        $token = $this->staff->csrfToken();
        $response = $this->staff->post('/api/menu/create', [
            'name' => $name,
            'description' => $name,
            'price_pence' => $pricePence,
            'category' => 'Test Category',
            'is_available' => true,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $response['status']);

        return (int)($response['json']['item']['id'] ?? 0);
    }

    private function createTempPng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'menu_img_');
        if (!is_string($path) || $path === '')
        {
            throw new \RuntimeException('Unable to create temp image file');
        }

        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgR2j6xYAAAAASUVORK5CYII=');
        if ($pngBinary === false)
        {
            throw new \RuntimeException('Unable to generate png payload');
        }

        file_put_contents($path, $pngBinary);

        return $path;
    }

    private function createTempTextFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'menu_txt_');
        if (!is_string($path) || $path === '')
        {
            throw new \RuntimeException('Unable to create temp text file');
        }

        file_put_contents($path, 'not-an-image');

        return $path;
    }
}
