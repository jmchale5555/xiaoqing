#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/app/core/config.php';
require dirname(__DIR__) . '/app/core/Migration.php';
require dirname(__DIR__) . '/app/core/Schema.php';
require dirname(__DIR__) . '/app/core/MigrationRunner.php';

use Core\MigrationRunner;

$command = $argv[1] ?? 'help';
$name = $argv[2] ?? null;

$runner = new MigrationRunner(dirname(__DIR__) . '/database/migrations');

switch ($command)
{
    case 'make':
        if (empty($name))
        {
            fwrite(STDERR, "Usage: php cli/migrate.php make <migration_name>\n");
            exit(1);
        }

        $path = $runner->make($name);
        fwrite(STDOUT, "Created: {$path}\n");
        break;

    case 'up':
        $runner->up();
        break;

    case 'down':
        $runner->down();
        break;

    case 'reset':
        $runner->reset();
        break;

    case 'fresh':
        $runner->fresh();
        break;

    case 'status':
        $runner->status();
        break;

    default:
        fwrite(STDOUT, "Migration commands:\n");
        fwrite(STDOUT, "  php cli/migrate.php make <name>\n");
        fwrite(STDOUT, "  php cli/migrate.php up\n");
        fwrite(STDOUT, "  php cli/migrate.php down\n");
        fwrite(STDOUT, "  php cli/migrate.php reset\n");
        fwrite(STDOUT, "  php cli/migrate.php fresh\n");
        fwrite(STDOUT, "  php cli/migrate.php status\n");
        break;
}
