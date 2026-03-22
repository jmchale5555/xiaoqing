<?php

namespace Tests\Support;

class TestDb
{
    public static function migrateFresh(): void
    {
        self::runCli('fresh');
    }

    public static function runCli(string $command): string
    {
        $root = dirname(__DIR__, 2);
        $envPrefix = self::envPrefix();
        $cmd = $envPrefix . ' php cli/migrate.php ' . escapeshellarg($command) . ' 2>&1';

        $cwd = getcwd();
        chdir($root);
        $output = shell_exec($cmd);
        chdir($cwd ?: $root);

        if (!is_string($output))
        {
            throw new \RuntimeException('Migration command failed to execute');
        }

        return $output;
    }

    private static function envPrefix(): string
    {
        $map = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'true',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => self::env('TEST_DB_HOST', 'db'),
            'DB_PORT' => self::env('TEST_DB_PORT', '3306'),
            'DB_NAME' => self::env('TEST_DB_NAME', 'phpsk_test'),
            'DB_USER' => self::env('TEST_DB_USER', 'phpsk'),
            'DB_PASS' => self::env('TEST_DB_PASS', 'phpsk_dev_password'),
            'APP_URL' => self::env('TEST_BASE_URL', 'http://nginx_test'),
        ];

        $parts = [];
        foreach ($map as $key => $value)
        {
            $parts[] = $key . '=' . escapeshellarg($value);
        }

        return implode(' ', $parts);
    }

    private static function env(string $key, string $default): string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '')
        {
            return $default;
        }

        return $value;
    }
}
