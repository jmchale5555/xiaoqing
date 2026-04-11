<?php

namespace Controller\Api;

use Core\ApiController;
use Core\Request;
use Core\Session;
use Model\MenuItem;
use Throwable;

defined('ROOTPATH') or exit('Access Denied');

class MenuController extends ApiController
{
    /**
     * Endpoints:
     * - GET /api/menu
     * - GET /api/menu/show/{id}
     * - POST /api/menu/create
     * - POST /api/menu/update/{id}
     * - POST /api/menu/delete/{id}
     * - POST /api/menu/reorder
     */
    public function index(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $menu = new MenuItem();
        $where = [];

        if (isset($_GET['category']) && trim((string)$_GET['category']) !== '')
        {
            $where['category'] = trim((string)$_GET['category']);
        }

        if (isset($_GET['is_available']) && trim((string)$_GET['is_available']) !== '')
        {
            $available = $this->normalizeBool($_GET['is_available']);
            if ($available !== null)
            {
                $where['is_available'] = $available ? 1 : 0;
            }
        }

        try
        {
            if (!empty($where))
            {
                $rows = $menu->where($where, [], [], 1000, 0, 'display_order', 'asc', ['display_order', 'id']);
            }
            else
            {
                $rows = $menu->all(1000, 0, 'display_order', 'asc', ['display_order', 'id']);
            }
        }
        catch (Throwable $e)
        {
            $this->error('Menu service unavailable', 500);
            return;
        }

        if (!is_array($rows))
        {
            $rows = [];
        }

        $this->ok([
            'items' => array_map([$this, 'formatMenuItem'], $rows),
        ]);
    }

    public function show(string $id = ''): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $itemId = (int)$id;
        if ($itemId <= 0)
        {
            $this->validationError(['id' => 'Invalid menu item id']);
            return;
        }

        $menu = new MenuItem();

        try
        {
            $row = $menu->first(['id' => $itemId]);
        }
        catch (Throwable $e)
        {
            $this->error('Menu service unavailable', 500);
            return;
        }

        if (!$row)
        {
            $this->notFound('Menu item not found');
            return;
        }

        $this->ok(['item' => $this->formatMenuItem($row)]);
    }

    public function create(): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload) || !$this->requireAuthenticatedUser())
        {
            return;
        }

        [$data, $errors] = $this->validateForCreate($payload);
        if (!empty($errors))
        {
            $this->validationError($errors);
            return;
        }

        $menu = new MenuItem();

        try
        {
            if (!array_key_exists('display_order', $data))
            {
                $data['display_order'] = $this->nextDisplayOrder($menu);
            }

            $menu->insert($data);
            $createdRows = $menu->where(['name' => $data['name']], [], [], 1, 0, 'id', 'desc', ['id']);
            $created = is_array($createdRows) && isset($createdRows[0]) ? $createdRows[0] : null;
        }
        catch (Throwable $e)
        {
            $this->error('Unable to create menu item', 500);
            return;
        }

        if (!$created)
        {
            $this->error('Unable to create menu item', 500);
            return;
        }

        $this->ok(['item' => $this->formatMenuItem($created)], 201);
    }

    public function update(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $itemId = (int)$id;
        if ($itemId <= 0)
        {
            $this->validationError(['id' => 'Invalid menu item id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload) || !$this->requireAuthenticatedUser())
        {
            return;
        }

        $menu = new MenuItem();

        try
        {
            $existing = $menu->first(['id' => $itemId]);
        }
        catch (Throwable $e)
        {
            $this->error('Menu service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Menu item not found');
            return;
        }

        [$data, $errors] = $this->validateForUpdate($payload);
        if (!empty($errors))
        {
            $this->validationError($errors);
            return;
        }

        if (empty($data))
        {
            $this->validationError(['payload' => 'No updatable fields provided']);
            return;
        }

        try
        {
            $menu->update($itemId, $data);
            $updated = $menu->first(['id' => $itemId]);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to update menu item', 500);
            return;
        }

        if (!$updated)
        {
            $this->error('Unable to update menu item', 500);
            return;
        }

        $this->ok(['item' => $this->formatMenuItem($updated)]);
    }

    public function delete(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $itemId = (int)$id;
        if ($itemId <= 0)
        {
            $this->validationError(['id' => 'Invalid menu item id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload) || !$this->requireAuthenticatedUser())
        {
            return;
        }

        $menu = new MenuItem();

        try
        {
            $existing = $menu->first(['id' => $itemId]);
        }
        catch (Throwable $e)
        {
            $this->error('Menu service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Menu item not found');
            return;
        }

        try
        {
            $menu->delete($itemId);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to delete menu item', 500);
            return;
        }

        $this->ok(['ok' => true]);
    }

    public function reorder(): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload) || !$this->requireAuthenticatedUser())
        {
            return;
        }

        $ids = $payload['ids'] ?? null;
        if (!is_array($ids) || empty($ids))
        {
            $this->validationError(['ids' => 'ids must be a non-empty array']);
            return;
        }

        $orderedIds = [];
        foreach ($ids as $rawId)
        {
            $id = (int)$rawId;
            if ($id <= 0)
            {
                $this->validationError(['ids' => 'ids must contain positive integers only']);
                return;
            }

            $orderedIds[] = $id;
        }

        if (count(array_unique($orderedIds)) !== count($orderedIds))
        {
            $this->validationError(['ids' => 'ids must not contain duplicates']);
            return;
        }

        $menu = new MenuItem();

        try
        {
            $existingIds = $this->fetchExistingIds($menu, $orderedIds);
        }
        catch (Throwable $e)
        {
            $this->error('Menu service unavailable', 500);
            return;
        }

        if (count($existingIds) !== count($orderedIds))
        {
            $this->validationError(['ids' => 'One or more menu item ids do not exist']);
            return;
        }

        try
        {
            foreach ($orderedIds as $index => $id)
            {
                $menu->update($id, ['display_order' => $index]);
            }

            $rows = $menu->all(1000, 0, 'display_order', 'asc', ['display_order', 'id']);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to reorder menu items', 500);
            return;
        }

        if (!is_array($rows))
        {
            $rows = [];
        }

        $this->ok(['items' => array_map([$this, 'formatMenuItem'], $rows)]);
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

    private function validateForCreate(array $payload): array
    {
        $errors = [];
        $data = [];

        $name = trim((string)($payload['name'] ?? ''));
        if ($name === '')
        {
            $errors['name'] = 'Name is required';
        }
        elseif (mb_strlen($name) > 180)
        {
            $errors['name'] = 'Name must be 180 characters or less';
        }
        else
        {
            $data['name'] = $name;
        }

        $description = trim((string)($payload['description'] ?? ''));
        $data['description'] = $description !== '' ? $description : null;

        $category = trim((string)($payload['category'] ?? ''));
        if ($category !== '' && mb_strlen($category) > 120)
        {
            $errors['category'] = 'Category must be 120 characters or less';
        }
        else
        {
            $data['category'] = $category !== '' ? $category : null;
        }

        $imagePath = trim((string)($payload['image_path'] ?? ''));
        if ($imagePath !== '' && !$this->isSafeImagePath($imagePath))
        {
            $errors['image_path'] = 'image_path must start with /uploads/menu/';
        }
        else
        {
            $data['image_path'] = $imagePath !== '' ? $imagePath : null;
        }

        $price = $payload['price_pence'] ?? null;
        if ($price === null || $price === '')
        {
            $errors['price_pence'] = 'price_pence is required';
        }
        elseif (filter_var($price, FILTER_VALIDATE_INT) === false || (int)$price < 0)
        {
            $errors['price_pence'] = 'price_pence must be a non-negative integer';
        }
        else
        {
            $data['price_pence'] = (int)$price;
        }

        if (array_key_exists('display_order', $payload) && $payload['display_order'] !== '')
        {
            if (filter_var($payload['display_order'], FILTER_VALIDATE_INT) === false || (int)$payload['display_order'] < 0)
            {
                $errors['display_order'] = 'display_order must be a non-negative integer';
            }
            else
            {
                $data['display_order'] = (int)$payload['display_order'];
            }
        }

        $available = $this->normalizeBool($payload['is_available'] ?? 1);
        if ($available === null)
        {
            $errors['is_available'] = 'is_available must be true or false';
        }
        else
        {
            $data['is_available'] = $available ? 1 : 0;
        }

        return [$data, $errors];
    }

    private function validateForUpdate(array $payload): array
    {
        $errors = [];
        $data = [];

        if (array_key_exists('name', $payload))
        {
            $name = trim((string)$payload['name']);
            if ($name === '')
            {
                $errors['name'] = 'Name is required';
            }
            elseif (mb_strlen($name) > 180)
            {
                $errors['name'] = 'Name must be 180 characters or less';
            }
            else
            {
                $data['name'] = $name;
            }
        }

        if (array_key_exists('description', $payload))
        {
            $description = trim((string)$payload['description']);
            $data['description'] = $description !== '' ? $description : null;
        }

        if (array_key_exists('category', $payload))
        {
            $category = trim((string)$payload['category']);
            if ($category !== '' && mb_strlen($category) > 120)
            {
                $errors['category'] = 'Category must be 120 characters or less';
            }
            else
            {
                $data['category'] = $category !== '' ? $category : null;
            }
        }

        if (array_key_exists('image_path', $payload))
        {
            $imagePath = trim((string)$payload['image_path']);
            if ($imagePath !== '' && !$this->isSafeImagePath($imagePath))
            {
                $errors['image_path'] = 'image_path must start with /uploads/menu/';
            }
            else
            {
                $data['image_path'] = $imagePath !== '' ? $imagePath : null;
            }
        }

        if (array_key_exists('price_pence', $payload))
        {
            if (filter_var($payload['price_pence'], FILTER_VALIDATE_INT) === false || (int)$payload['price_pence'] < 0)
            {
                $errors['price_pence'] = 'price_pence must be a non-negative integer';
            }
            else
            {
                $data['price_pence'] = (int)$payload['price_pence'];
            }
        }

        if (array_key_exists('display_order', $payload))
        {
            if (filter_var($payload['display_order'], FILTER_VALIDATE_INT) === false || (int)$payload['display_order'] < 0)
            {
                $errors['display_order'] = 'display_order must be a non-negative integer';
            }
            else
            {
                $data['display_order'] = (int)$payload['display_order'];
            }
        }

        if (array_key_exists('is_available', $payload))
        {
            $available = $this->normalizeBool($payload['is_available']);
            if ($available === null)
            {
                $errors['is_available'] = 'is_available must be true or false';
            }
            else
            {
                $data['is_available'] = $available ? 1 : 0;
            }
        }

        return [$data, $errors];
    }

    private function normalizeBool(mixed $value): ?bool
    {
        if (is_bool($value))
        {
            return $value;
        }

        if (is_int($value))
        {
            if ($value === 1)
            {
                return true;
            }

            if ($value === 0)
            {
                return false;
            }

            return null;
        }

        $str = strtolower(trim((string)$value));
        if (in_array($str, ['1', 'true', 'yes', 'on'], true))
        {
            return true;
        }

        if (in_array($str, ['0', 'false', 'no', 'off'], true))
        {
            return false;
        }

        return null;
    }

    private function nextDisplayOrder(MenuItem $menu): int
    {
        $rows = $menu->query('select max(display_order) as max_order from menu_items');
        $max = isset($rows[0]->max_order) ? (int)$rows[0]->max_order : -1;

        return $max + 1;
    }

    private function fetchExistingIds(MenuItem $menu, array $ids): array
    {
        $placeholders = [];
        $params = [];

        foreach ($ids as $index => $id)
        {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }

        $sql = 'select id from menu_items where id in (' . implode(',', $placeholders) . ')';
        $rows = $menu->query($sql, $params);

        if (!is_array($rows))
        {
            return [];
        }

        $existing = [];
        foreach ($rows as $row)
        {
            $existing[] = (int)($row->id ?? 0);
        }

        return $existing;
    }

    private function isSafeImagePath(string $path): bool
    {
        return str_starts_with($path, '/uploads/menu/');
    }

    private function formatMenuItem(mixed $row): array
    {
        return [
            'id' => isset($row->id) ? (int)$row->id : null,
            'name' => (string)($row->name ?? ''),
            'description' => $row->description ?? null,
            'price_pence' => isset($row->price_pence) ? (int)$row->price_pence : 0,
            'category' => $row->category ?? null,
            'image_path' => $row->image_path ?? null,
            'display_order' => isset($row->display_order) ? (int)$row->display_order : 0,
            'is_available' => !empty($row->is_available),
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }
}
