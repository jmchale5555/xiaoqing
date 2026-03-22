<?php

namespace Tests\Feature;

use Tests\Support\HttpClient;
use Tests\TestCase;

class PostsApiTest extends TestCase
{
    private HttpClient $owner;

    private HttpClient $other;

    public static function setUpBeforeClass(): void
    {
        self::resetTestDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = new HttpClient(self::baseUrl());
        $this->other = new HttpClient(self::baseUrl());

        $this->signupClient($this->owner, 'owner' . str_replace('.', '', uniqid('', true)) . '@example.com');
        $this->signupClient($this->other, 'other' . str_replace('.', '', uniqid('', true)) . '@example.com');
    }

    public function testPostsIndexReturnsArray(): void
    {
        $response = $this->owner->get('/api/posts');

        $this->assertSame(200, $response['status']);
        $this->assertIsArray($response['json']['posts'] ?? null);
        $this->assertIsArray($response['json']['meta'] ?? null);
        $this->assertSame(1, $response['json']['meta']['page'] ?? null);
        $this->assertSame(20, $response['json']['meta']['per_page'] ?? null);
        $this->assertArrayHasKey('has_next', $response['json']['meta'] ?? []);
        $this->assertArrayHasKey('has_prev', $response['json']['meta'] ?? []);
    }

    public function testPostsIndexSupportsPaginationParameters(): void
    {
        $token = $this->owner->csrfToken();
        $this->owner->post('/api/posts/create', [
            'title' => 'P1',
            'body' => 'Body 1',
            'is_published' => true,
        ], ['X-CSRF-Token' => $token]);

        $token2 = $this->owner->csrfToken();
        $this->owner->post('/api/posts/create', [
            'title' => 'P2',
            'body' => 'Body 2',
            'is_published' => true,
        ], ['X-CSRF-Token' => $token2]);

        $response = $this->owner->get('/api/posts?page=1&per_page=1&sort=id&dir=asc');

        $this->assertSame(200, $response['status']);
        $this->assertSame(1, $response['json']['meta']['page'] ?? null);
        $this->assertSame(1, $response['json']['meta']['per_page'] ?? null);
        $this->assertGreaterThanOrEqual(2, $response['json']['meta']['total'] ?? 0);
        $this->assertCount(1, $response['json']['posts'] ?? []);
    }

    public function testPostsIndexInvalidSortFallsBackSafely(): void
    {
        $response = $this->owner->get('/api/posts?sort=drop_table_posts&dir=sideways&per_page=500');

        $this->assertSame(200, $response['status']);
        $this->assertSame(100, $response['json']['meta']['per_page'] ?? null);
        $this->assertSame(1, $response['json']['meta']['page'] ?? null);
    }

    public function testCreateAndShowPost(): void
    {
        $token = $this->owner->csrfToken();
        $create = $this->owner->post('/api/posts/create', [
            'title' => 'Forum first post',
            'body' => 'Hello forum',
            'is_published' => true,
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $create['status']);
        $postId = (int)($create['json']['post']['id'] ?? 0);
        $this->assertGreaterThan(0, $postId);

        $show = $this->owner->get('/api/posts/show/' . $postId);
        $this->assertSame(200, $show['status']);
        $this->assertSame($postId, (int)($show['json']['post']['id'] ?? 0));
    }

    public function testCreateRequiresAuthAndCsrf(): void
    {
        $guest = new HttpClient(self::baseUrl());
        $token = $guest->csrfToken();

        $create = $guest->post('/api/posts/create', [
            'title' => 'Guest post',
            'body' => 'Should not work',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(401, $create['status']);
    }

    public function testUpdateAndDeleteAreOwnerOnly(): void
    {
        $token = $this->owner->csrfToken();
        $create = $this->owner->post('/api/posts/create', [
            'title' => 'Owner post',
            'body' => 'Body',
            'is_published' => false,
        ], ['X-CSRF-Token' => $token]);

        $postId = (int)($create['json']['post']['id'] ?? 0);
        $this->assertGreaterThan(0, $postId);

        $otherToken = $this->other->csrfToken();
        $forbiddenUpdate = $this->other->post('/api/posts/update/' . $postId, [
            'title' => 'Other edit',
            'body' => 'Nope',
            'is_published' => true,
        ], ['X-CSRF-Token' => $otherToken]);
        $this->assertSame(403, $forbiddenUpdate['status']);

        $forbiddenDelete = $this->other->post('/api/posts/delete/' . $postId, [], ['X-CSRF-Token' => $otherToken]);
        $this->assertSame(403, $forbiddenDelete['status']);

        $ownerToken = $this->owner->csrfToken();
        $delete = $this->owner->post('/api/posts/delete/' . $postId, [], ['X-CSRF-Token' => $ownerToken]);
        $this->assertSame(200, $delete['status']);

        $missing = $this->owner->get('/api/posts/show/' . $postId);
        $this->assertSame(404, $missing['status']);
    }

    private function signupClient(HttpClient $client, string $email): void
    {
        $token = $client->csrfToken();
        $response = $client->post('/api/auth/signup', [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'secret123',
            'confirm' => 'secret123',
        ], ['X-CSRF-Token' => $token]);

        $this->assertSame(201, $response['status']);
    }
}
