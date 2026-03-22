<?php

function loadEnvFile(string $file): void
{
    if (!is_file($file) || !is_readable($file))
    {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines))
    {
        return;
    }

    foreach ($lines as $line)
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#'))
        {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2)
        {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '' || getenv($key) !== false)
        {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        )
        {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

loadEnvFile(dirname(__DIR__, 2) . '/.env');

function envValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false)
    {
        return $default;
    }

    $trimmed = trim($value);
    if ($trimmed === '')
    {
        return $default;
    }

    return $trimmed;
}

function envBool(string $key, bool $default = false): bool
{
    $value = envValue($key);
    if ($value === null)
    {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

define('DBDRIVER', envValue('DB_DRIVER', 'mysql'));
define('DBHOST', envValue('DB_HOST', 'localhost'));
define('DBPORT', envValue('DB_PORT', '3306'));
define('DBNAME', envValue('DB_NAME', 'phpsk'));
define('DBUSER', envValue('DB_USER', 'phpsk'));
define('DBPASS', envValue('DB_PASS', 'phpsk'));

define('ROOT', envValue('APP_URL', 'http://localhost:8080'));

define('APP_NAME', envValue('APP_NAME', 'PHP SPA Boilerplate'));
define('APP_DESC', envValue('APP_DESC', 'Reusable PHP API and React SPA starter'));
define('APP_ENV', envValue('APP_ENV', 'development'));
define('DEBUG_MODE', envBool('APP_DEBUG', APP_ENV !== 'production'));
