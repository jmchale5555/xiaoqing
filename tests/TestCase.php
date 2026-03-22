<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Support\TestDb;

abstract class TestCase extends BaseTestCase
{
    protected static function resetTestDatabase(): void
    {
        TestDb::migrateFresh();
    }

    protected static function baseUrl(): string
    {
        $url = getenv('TEST_BASE_URL');
        if ($url === false || trim($url) === '')
        {
            return 'http://nginx_test';
        }

        return rtrim($url, '/');
    }
}
