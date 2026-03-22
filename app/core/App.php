<?php

class App
{
    private function splitURL()
    {
        $URL = $_GET['url'] ?? '';
        $URL = explode("/", trim($URL, '/'));

        return $URL;
    }

    public function loadController()
    {
        $URL = $this->splitURL();

        if (($URL[0] ?? '') === 'api')
        {
            $this->loadApiController($URL);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET')
        {
            require ROOTPATH . 'spa.php';
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => 'Not found']);
    }

    private function loadApiController(array $URL): void
    {
        /**
         * API route pattern:
         * /api/{resource}/{method?}/{param1?}/{param2?...}
         * method defaults to "index" when omitted.
         */
        header('Content-Type: application/json; charset=utf-8');

        $resource = ucfirst($URL[1] ?? '');
        $method = $URL[2] ?? 'index';
        $params = array_values(array_slice($URL, 3));

        if (empty($resource))
        {
            http_response_code(404);
            echo json_encode(['message' => 'API resource not found']);
            return;
        }

        $controllerName = $resource . 'Controller';
        $file = "../app/controllers/Api/{$controllerName}.php";

        if (!file_exists($file))
        {
            http_response_code(404);
            echo json_encode(['message' => 'API controller not found']);
            return;
        }

        require $file;

        $class = '\\Controller\\Api\\' . $controllerName;
        $controller = new $class;

        if (!method_exists($controller, $method))
        {
            http_response_code(404);
            echo json_encode(['message' => 'API endpoint not found']);
            return;
        }

        try
        {
            call_user_func_array([$controller, $method], $params);
        }
        catch (\Throwable $e)
        {
            http_response_code(500);
            echo json_encode(['message' => 'Server error']);
        }
    }
}
