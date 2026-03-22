<?php

namespace Tests\Integration;

use Tests\Support\TestDb;
use Tests\TestCase;

class MigrationCliTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        self::resetTestDatabase();
    }

    public function testStatusShowsAppliedMigrations(): void
    {
        $output = TestDb::runCli('status');

        $this->assertStringContainsString('[Y] 20260316_000000_create_users_table.php', $output);
        $this->assertStringContainsString('[Y] 20260316_000100_create_posts_table.php', $output);
    }

    public function testDownAndUpCommandsWork(): void
    {
        $down = TestDb::runCli('down');
        $this->assertStringContainsString('Rolled back', $down);

        $statusAfterDown = TestDb::runCli('status');
        $this->assertStringContainsString('[N] 20260316_000100_create_posts_table.php', $statusAfterDown);

        $up = TestDb::runCli('up');
        $this->assertStringContainsString('Applied 20260316_000100_create_posts_table.php', $up);
    }

    public function testMakeCreatesMigrationFileTemplate(): void
    {
        $name = 'create_comments_table_test_' . time();
        $root = dirname(__DIR__, 2);

        $cmd = 'php cli/migrate.php make ' . escapeshellarg($name) . ' 2>&1';
        $cwd = getcwd();
        chdir($root);
        $output = shell_exec($cmd);
        chdir($cwd ?: $root);

        $this->assertIsString($output);
        $this->assertStringContainsString('Created:', $output);

        preg_match('/Created:\s+(.+)\n?/', $output, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $file = trim((string)$matches[1]);
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertIsString($contents);
        $this->assertStringContainsString('extends Migration', $contents);
        $this->assertStringContainsString('public function up(Schema $schema): void', $contents);
        $this->assertStringContainsString('public function down(Schema $schema): void', $contents);

        unlink($file);
    }
}
