<?php

namespace Tests\Unit\Core;

use Core\ApiController;
use Tests\TestCase;

class ApiControllerTest extends TestCase
{
    public function testOkAndErrorPayloadShapes(): void
    {
        $controller = new class extends ApiController {
            public function sendOk(array $payload = [], int $status = 200): void
            {
                $this->ok($payload, $status);
            }

            public function sendError(string $message, int $status = 400, array $errors = []): void
            {
                $this->error($message, $status, $errors);
            }
        };

        ob_start();
        $controller->sendOk(['ok' => true], 201);
        $okBody = ob_get_clean();

        $this->assertSame(201, http_response_code());
        $this->assertSame('{"ok":true}', $okBody);

        ob_start();
        $controller->sendError('Validation failed', 422, ['title' => 'required']);
        $errorBody = ob_get_clean();

        $this->assertSame(422, http_response_code());
        $this->assertSame('{"message":"Validation failed","errors":{"title":"required"}}', $errorBody);
    }
}
