<?php

namespace Core;

defined('ROOTPATH') or exit('Access Denied!');

class ApiController
{
    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }

    protected function ok(array $payload = [], int $status = 200): void
    {
        $this->json($payload, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): void
    {
        $payload = ['message' => $message];

        if (!empty($errors))
        {
            $payload['errors'] = $errors;
        }

        $this->json($payload, $status);
    }

    protected function methodNotAllowed(array $allowed = []): void
    {
        if (!empty($allowed))
        {
            header('Allow: ' . implode(', ', $allowed));
        }

        $this->error('Method not allowed', 405);
    }

    protected function unauthenticated(string $message = 'Unauthenticated'): void
    {
        $this->error($message, 401);
    }

    protected function validationError(array $errors, string $message = 'Validation failed'): void
    {
        $this->error($message, 422, $errors);
    }

    protected function notFound(string $message = 'Not found'): void
    {
        $this->error($message, 404);
    }
}
