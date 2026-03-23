<?php

namespace Controller\Api;

use Core\ApiController;
use Core\Request;
use Core\Session;
use Model\DiningTable;
use Throwable;

defined('ROOTPATH') or exit('Access Denied');

class TablesController extends ApiController
{
    public function index(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $table = new DiningTable();
        $where = [];

        if (isset($_GET['is_active']) && trim((string)$_GET['is_active']) !== '')
        {
            $active = $this->normalizeBool($_GET['is_active']);
            if ($active !== null)
            {
                $where['is_active'] = $active ? 1 : 0;
            }
        }

        try
        {
            if (!empty($where))
            {
                $rows = $table->where($where, [], [], 1000, 0, 'display_order', 'asc', ['display_order', 'id']);
            }
            else
            {
                $rows = $table->all(1000, 0, 'display_order', 'asc', ['display_order', 'id']);
            }
        }
        catch (Throwable $e)
        {
            $this->error('Tables service unavailable', 500);
            return;
        }

        if (!is_array($rows))
        {
            $rows = [];
        }

        $this->ok([
            'tables' => array_map([$this, 'formatTable'], $rows),
        ]);
    }

    public function show(string $id = ''): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET')
        {
            $this->methodNotAllowed(['GET']);
            return;
        }

        $tableId = (int)$id;
        if ($tableId <= 0)
        {
            $this->validationError(['id' => 'Invalid table id']);
            return;
        }

        $table = new DiningTable();

        try
        {
            $row = $table->first(['id' => $tableId]);
        }
        catch (Throwable $e)
        {
            $this->error('Tables service unavailable', 500);
            return;
        }

        if (!$row)
        {
            $this->notFound('Table not found');
            return;
        }

        $this->ok(['table' => $this->formatTable($row)]);
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

        $table = new DiningTable();

        try
        {
            if (!array_key_exists('display_order', $data))
            {
                $data['display_order'] = $this->nextDisplayOrder($table);
            }

            $table->insert($data);
            $createdRows = $table->where(['name' => $data['name']], [], [], 1, 0, 'id', 'desc', ['id']);
            $created = is_array($createdRows) && isset($createdRows[0]) ? $createdRows[0] : null;
        }
        catch (Throwable $e)
        {
            $this->error('Unable to create table', 500);
            return;
        }

        if (!$created)
        {
            $this->error('Unable to create table', 500);
            return;
        }

        $this->ok(['table' => $this->formatTable($created)], 201);
    }

    public function update(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $tableId = (int)$id;
        if ($tableId <= 0)
        {
            $this->validationError(['id' => 'Invalid table id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload) || !$this->requireAuthenticatedUser())
        {
            return;
        }

        $table = new DiningTable();

        try
        {
            $existing = $table->first(['id' => $tableId]);
        }
        catch (Throwable $e)
        {
            $this->error('Tables service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Table not found');
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
            $table->update($tableId, $data);
            $updated = $table->first(['id' => $tableId]);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to update table', 500);
            return;
        }

        if (!$updated)
        {
            $this->error('Unable to update table', 500);
            return;
        }

        $this->ok(['table' => $this->formatTable($updated)]);
    }

    public function delete(string $id = ''): void
    {
        if (!$this->requireWriteMethod())
        {
            return;
        }

        $tableId = (int)$id;
        if ($tableId <= 0)
        {
            $this->validationError(['id' => 'Invalid table id']);
            return;
        }

        $payload = $this->readPayload();
        if (!$this->verifyCsrfToken($payload) || !$this->requireAuthenticatedUser())
        {
            return;
        }

        $table = new DiningTable();

        try
        {
            $existing = $table->first(['id' => $tableId]);
        }
        catch (Throwable $e)
        {
            $this->error('Tables service unavailable', 500);
            return;
        }

        if (!$existing)
        {
            $this->notFound('Table not found');
            return;
        }

        try
        {
            $table->delete($tableId);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to delete table', 500);
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

        $table = new DiningTable();

        try
        {
            $existingIds = $this->fetchExistingIds($table, $orderedIds);
        }
        catch (Throwable $e)
        {
            $this->error('Tables service unavailable', 500);
            return;
        }

        if (count($existingIds) !== count($orderedIds))
        {
            $this->validationError(['ids' => 'One or more table ids do not exist']);
            return;
        }

        try
        {
            foreach ($orderedIds as $index => $id)
            {
                $table->update($id, ['display_order' => $index]);
            }

            $rows = $table->all(1000, 0, 'display_order', 'asc', ['display_order', 'id']);
        }
        catch (Throwable $e)
        {
            $this->error('Unable to reorder tables', 500);
            return;
        }

        if (!is_array($rows))
        {
            $rows = [];
        }

        $this->ok(['tables' => array_map([$this, 'formatTable'], $rows)]);
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
        elseif (mb_strlen($name) > 120)
        {
            $errors['name'] = 'Name must be 120 characters or less';
        }
        else
        {
            $data['name'] = $name;
        }

        $seats = $payload['seats'] ?? null;
        if ($seats === null || $seats === '')
        {
            $errors['seats'] = 'seats is required';
        }
        elseif (filter_var($seats, FILTER_VALIDATE_INT) === false || (int)$seats < 1)
        {
            $errors['seats'] = 'seats must be a positive integer';
        }
        else
        {
            $data['seats'] = (int)$seats;
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

        $active = $this->normalizeBool($payload['is_active'] ?? 1);
        if ($active === null)
        {
            $errors['is_active'] = 'is_active must be true or false';
        }
        else
        {
            $data['is_active'] = $active ? 1 : 0;
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
            elseif (mb_strlen($name) > 120)
            {
                $errors['name'] = 'Name must be 120 characters or less';
            }
            else
            {
                $data['name'] = $name;
            }
        }

        if (array_key_exists('seats', $payload))
        {
            if (filter_var($payload['seats'], FILTER_VALIDATE_INT) === false || (int)$payload['seats'] < 1)
            {
                $errors['seats'] = 'seats must be a positive integer';
            }
            else
            {
                $data['seats'] = (int)$payload['seats'];
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

        if (array_key_exists('is_active', $payload))
        {
            $active = $this->normalizeBool($payload['is_active']);
            if ($active === null)
            {
                $errors['is_active'] = 'is_active must be true or false';
            }
            else
            {
                $data['is_active'] = $active ? 1 : 0;
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

    private function nextDisplayOrder(DiningTable $table): int
    {
        $rows = $table->query('select max(display_order) as max_order from tables');
        $max = isset($rows[0]->max_order) ? (int)$rows[0]->max_order : -1;

        return $max + 1;
    }

    private function fetchExistingIds(DiningTable $table, array $ids): array
    {
        $placeholders = [];
        $params = [];

        foreach ($ids as $index => $id)
        {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }

        $sql = 'select id from tables where id in (' . implode(',', $placeholders) . ')';
        $rows = $table->query($sql, $params);

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

    private function formatTable(mixed $row): array
    {
        return [
            'id' => isset($row->id) ? (int)$row->id : null,
            'name' => (string)($row->name ?? ''),
            'seats' => isset($row->seats) ? (int)$row->seats : 0,
            'is_active' => !empty($row->is_active),
            'display_order' => isset($row->display_order) ? (int)$row->display_order : 0,
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ];
    }
}
