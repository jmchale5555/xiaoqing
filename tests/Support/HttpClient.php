<?php

namespace Tests\Support;

class HttpClient
{
    private string $baseUrl;

    private string $cookieFile;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'phpsk_test_cookie_');
    }

    public function __destruct()
    {
        if (is_file($this->cookieFile))
        {
            @unlink($this->cookieFile);
        }
    }

    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, null, $headers);
    }

    public function post(string $path, ?array $payload = null, array $headers = []): array
    {
        return $this->request('POST', $path, $payload, $headers);
    }

    public function postMultipart(string $path, array $fields = [], array $files = [], array $headers = []): array
    {
        return $this->requestMultipart('POST', $path, $fields, $files, $headers);
    }

    public function request(string $method, string $path, ?array $payload = null, array $headers = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        if ($ch === false)
        {
            throw new \RuntimeException('Unable to initialize curl');
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $this->buildHeaders($headers, $payload !== null ? 'application/json' : null),
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2)
                {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        if ($payload !== null)
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $body = curl_exec($ch);
        if ($body === false)
        {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = null;
        $contentType = $responseHeaders['content-type'] ?? '';
        if (str_contains(strtolower($contentType), 'application/json'))
        {
            $decoded = json_decode($body, true);
            if (is_array($decoded))
            {
                $json = $decoded;
            }
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $body,
            'json' => $json,
        ];
    }

    public function requestMultipart(string $method, string $path, array $fields = [], array $files = [], array $headers = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        if ($ch === false)
        {
            throw new \RuntimeException('Unable to initialize curl');
        }

        $responseHeaders = [];
        $postFields = $fields;

        foreach ($files as $key => $filePath)
        {
            if (!is_string($filePath) || $filePath === '' || !is_file($filePath))
            {
                throw new \RuntimeException('Multipart file path is invalid: ' . (string)$filePath);
            }

            $mime = mime_content_type($filePath);
            if (!is_string($mime) || $mime === '')
            {
                $mime = 'application/octet-stream';
            }

            $postFields[$key] = new \CURLFile($filePath, $mime, basename($filePath));
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $this->buildHeaders($headers, null),
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2)
                {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ]);

        $body = curl_exec($ch);
        if ($body === false)
        {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = null;
        $contentType = $responseHeaders['content-type'] ?? '';
        if (str_contains(strtolower($contentType), 'application/json'))
        {
            $decoded = json_decode($body, true);
            if (is_array($decoded))
            {
                $json = $decoded;
            }
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $body,
            'json' => $json,
        ];
    }

    public function csrfToken(): string
    {
        $response = $this->get('/api/auth/csrf');
        $token = $response['json']['csrfToken'] ?? '';

        if (!is_string($token) || $token === '')
        {
            throw new \RuntimeException('Unable to fetch CSRF token');
        }

        return $token;
    }

    private function buildHeaders(array $headers, ?string $contentType): array
    {
        $result = ['Accept: application/json'];

        if (is_string($contentType) && $contentType !== '')
        {
            $result[] = 'Content-Type: ' . $contentType;
        }

        foreach ($headers as $key => $value)
        {
            if (is_string($key))
            {
                $result[] = $key . ': ' . $value;
            }
            else
            {
                $result[] = (string)$value;
            }
        }

        return $result;
    }
}
