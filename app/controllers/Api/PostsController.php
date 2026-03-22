<?php

namespace Controller\Api;

use Core\ApiController;
use Core\Request;
use Core\Session;
use Model\Post;
use Throwable;

defined('ROOTPATH') or exit('Access Denied');

class PostsController extends ApiController
{
    public function index(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $post = new Post();
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 20);
        $sort = trim((string)($_GET['sort'] ?? 'id'));
        $dir = trim((string)($_GET['dir'] ?? 'desc'));

        try
        {
            $result = $post->paginate(
                [],
                $page,
                $perPage,
                $sort,
                $dir,
                ['id', 'created_at', 'updated_at', 'published_at', 'title']
            );
        }
        catch (Throwable $e)
        {
            $this->error('Posts service unavailable', 500);
            return;
        }

        $posts = array_map([$this, 'formatPost'], $result['items']);
        $this->ok([
            'posts' => $posts,
            'meta' => $result['meta'],
        ]);
    }

    public function show(string $id = ''): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $postId = (int)$id;
        if ($postId <= 0)
        {
            $this->validationError(['id' => 'Invalid post id']);
            return;
        }

        $post = new Post();

        try
        {
            $row = $post->first(['id' => $postId]);
        }
        catch (Throwable $e)
        {
            $this->error('Posts service unavailable', 500);
            return;
        }

        if (!$row)
        {
            $this->notFound('Post not found');
            return;
        }

        $this->ok(['post' => $this->formatPost($row)]);
    }

    public function create(): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload))
        {
            return;
        }

        $sessionUser = $this->requireAuthenticatedUser();
        if (!$sessionUser)
        {
            return;
        }

        $errors = $this->validatePayload($payload);
        if (!empty($errors))
        {
            $this->validationError($errors);
            return;
        }

        $post = new Post();

        try
        {
            $slug = $this->makeUniqueSlug($post, (string)$payload['title']);

            $data = [
                'user_id' => (int)$sessionUser->id,
                'title' => trim((string)$payload['title']),
                'body' => trim((string)$payload['body']),
                'slug' => $slug,
                'is_published' => !empty($payload['is_published']) ? 1 : 0,
                'published_at' => !empty($payload['is_published']) ? date('Y-m-d H:i:s') : null,
            ];

            $post->insert($data);
            $created = $post->first(['slug' => $slug]);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to create post', 500);
            return;
        }

        if (!$created)
        {
            $this->error('Unable to create post', 500);
            return;
        }

        $this->ok(['post' => $this->formatPost($created)], 201);
    }

    public function update(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $postId = (int)$id;
        if ($postId <= 0)
        {
            $this->validationError(['id' => 'Invalid post id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload))
        {
            return;
        }

        $sessionUser = $this->requireAuthenticatedUser();
        if (!$sessionUser)
        {
            return;
        }

        $errors = $this->validatePayload($payload);
        if (!empty($errors))
        {
            $this->validationError($errors);
            return;
        }

        $post = new Post();

        try
        {
            $existing = $post->first(['id' => $postId]);
        }
        catch (Throwable $e)
        {
            $this->error('Posts service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Post not found');
            return;
        }

        if ((int)($existing->user_id ?? 0) !== (int)($sessionUser->id ?? 0))
        {
            $this->error('Forbidden', 403);
            return;
        }

        try
        {
            $slug = trim((string)$payload['title']) === (string)$existing->title
                ? (string)$existing->slug
                : $this->makeUniqueSlug($post, (string)$payload['title'], $postId);

            $data = [
                'title' => trim((string)$payload['title']),
                'body' => trim((string)$payload['body']),
                'slug' => $slug,
                'is_published' => !empty($payload['is_published']) ? 1 : 0,
                'published_at' => !empty($payload['is_published'])
                    ? ((string)($existing->published_at ?? '') !== '' ? $existing->published_at : date('Y-m-d H:i:s'))
                    : null,
            ];

            $post->update($postId, $data);
            $updated = $post->first(['id' => $postId]);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to update post', 500);
            return;
        }

        $this->ok(['post' => $this->formatPost($updated)]);
    }

    public function delete(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $postId = (int)$id;
        if ($postId <= 0)
        {
            $this->validationError(['id' => 'Invalid post id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload))
        {
            return;
        }

        $sessionUser = $this->requireAuthenticatedUser();
        if (!$sessionUser)
        {
            return;
        }

        $post = new Post();

        try
        {
            $existing = $post->first(['id' => $postId]);
        }
        catch (Throwable $e)
        {
            $this->error('Posts service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Post not found');
            return;
        }

        if ((int)($existing->user_id ?? 0) !== (int)($sessionUser->id ?? 0))
        {
            $this->error('Forbidden', 403);
            return;
        }

        try
        {
            $post->delete($postId);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to delete post', 500);
            return;
        }

        $this->ok(['ok' => true]);
    }

    private function requireWriteMethod(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST' && $method !== 'PUT' && $method !== 'PATCH' && $method !== 'DELETE')
        {
            $this->methodNotAllowed(['POST', 'PUT', 'PATCH', 'DELETE']);
            return false;
        }

        return true;
    }

    private function readPayload(): array
    {
        $request = new Request();
        $payload = $request->json();

        if (empty($payload))
        {
            $payload = $request->post();
        }

        return is_array($payload) ? $payload : [];
    }

    private function requireAuthenticatedUser(): mixed
    {
        $session = new Session();
        $user = $session->user();

        if (!$user)
        {
            $this->unauthenticated();
            return null;
        }

        return $user;
    }

    private function verifyCsrfToken(array $payload = []): bool
    {
        $session = new Session();
        $expected = (string)$session->get('csrf_token', '');

        if ($expected === '')
        {
            $this->error('Invalid CSRF token', 419);
            return false;
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

    private function validatePayload(array $payload): array
    {
        $errors = [];

        $title = trim((string)($payload['title'] ?? ''));
        $body = trim((string)($payload['body'] ?? ''));

        if ($title === '')
        {
            $errors['title'] = 'Title is required';
        }
        elseif (strlen($title) > 160)
        {
            $errors['title'] = 'Title must be 160 characters or less';
        }

        if ($body === '')
        {
            $errors['body'] = 'Body is required';
        }

        return $errors;
    }

    private function makeUniqueSlug(Post $post, string $title, int $ignoreId = 0): string
    {
        $base = $this->slugify($title);
        if ($base === '')
        {
            $base = 'post';
        }

        $slug = $base;
        $counter = 2;

        while (true)
        {
            $existing = $post->first(['slug' => $slug]);
            if (!$existing)
            {
                return $slug;
            }

            if ($ignoreId > 0 && (int)($existing->id ?? 0) === $ignoreId)
            {
                return $slug;
            }

            $slug = $base . '-' . $counter;
            $counter++;
        }
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string)$value, '-');

        return $value;
    }

    private function formatPost(mixed $row): array
    {
        return [
            'id' => isset($row->id) ? (int)$row->id : null,
            'user_id' => isset($row->user_id) ? (int)$row->user_id : null,
            'title' => $row->title ?? '',
            'body' => $row->body ?? '',
            'slug' => $row->slug ?? '',
            'is_published' => !empty($row->is_published),
            'published_at' => $row->published_at ?? null,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }
}
