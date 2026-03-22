<?php

namespace Core;

use PDO;
use Throwable;

class MigrationRunner
{
    private string $migrationPath;

    private PDO $pdo;

    public function __construct(string $migrationPath)
    {
        $this->migrationPath = rtrim($migrationPath, '/');
        $this->pdo = $this->connect();
        $this->ensureMigrationsTable();
    }

    public function make(string $name): string
    {
        if (!is_dir($this->migrationPath))
        {
            mkdir($this->migrationPath, 0755, true);
        }

        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $name), '_'));
        $class = $this->studly($slug);
        $timestamp = date('Ymd_His');
        $filename = "{$timestamp}_{$slug}.php";
        $path = $this->migrationPath . '/' . $filename;

        $template = "<?php\n\n";
        $template .= "use Core\\Migration;\n";
        $template .= "use Core\\Schema;\n\n";
        $template .= "class {$class} extends Migration\n";
        $template .= "{\n";
        $template .= "    public function up(Schema \$schema): void\n";
        $template .= "    {\n";
        $template .= "        // Define schema changes\n";
        $template .= "    }\n\n";
        $template .= "    public function down(Schema \$schema): void\n";
        $template .= "    {\n";
        $template .= "        // Reverse schema changes\n";
        $template .= "    }\n";
        $template .= "}\n";

        file_put_contents($path, $template);

        return $path;
    }

    public function up(): void
    {
        $files = $this->migrationFiles();
        $applied = $this->appliedMap();
        $pending = array_values(array_filter($files, function (string $file) use ($applied) {
            return !isset($applied[basename($file)]);
        }));

        if (empty($pending))
        {
            $this->line('No pending migrations.');
            return;
        }

        $batch = $this->nextBatchNumber();
        foreach ($pending as $file)
        {
            $migration = basename($file);
            [$className, $instance] = $this->loadMigration($file);

            $this->line("Applying {$migration} ({$className})");

            try
            {
                $instance->up(new Schema($this->pdo));
                $this->recordMigration($migration, $batch);
                $this->line("Applied {$migration}");
            }
            catch (Throwable $e)
            {
                $this->line("Failed {$migration}: {$e->getMessage()}");
                throw $e;
            }
        }
    }

    public function down(): void
    {
        $batch = $this->lastBatchNumber();
        if ($batch === null)
        {
            $this->line('No migrations to rollback.');
            return;
        }

        $rows = $this->migrationsByBatch($batch);
        foreach ($rows as $row)
        {
            $migration = $row['migration'];
            $file = $this->migrationPath . '/' . $migration;

            if (!file_exists($file))
            {
                throw new \RuntimeException("Migration file missing: {$migration}");
            }

            [$className, $instance] = $this->loadMigration($file);
            $this->line("Rolling back {$migration} ({$className})");

            $instance->down(new Schema($this->pdo));
            $this->deleteMigrationRecord($migration);
            $this->line("Rolled back {$migration}");
        }
    }

    public function reset(): void
    {
        while ($this->lastBatchNumber() !== null)
        {
            $this->down();
        }
    }

    public function fresh(): void
    {
        $this->dropAllTables();
        $this->ensureMigrationsTable();
        $this->up();
    }

    public function status(): void
    {
        $files = $this->migrationFiles();
        $applied = $this->appliedMap();

        if (empty($files))
        {
            $this->line('No migration files found.');
            return;
        }

        foreach ($files as $file)
        {
            $migration = basename($file);
            if (isset($applied[$migration]))
            {
                $batch = $applied[$migration];
                $this->line("[Y] {$migration} (batch {$batch})");
            }
            else
            {
                $this->line("[N] {$migration}");
            }
        }
    }

    private function connect(): PDO
    {
        $dsn = DBDRIVER . ':host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';

        return new PDO($dsn, DBUSER, DBPASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS schema_migrations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY schema_migrations_migration_unique (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationPath))
        {
            return [];
        }

        $files = glob($this->migrationPath . '/*.php');
        if ($files === false)
        {
            return [];
        }

        sort($files);
        return $files;
    }

    private function loadMigration(string $file): array
    {
        $className = $this->classFromFilename(basename($file));
        require_once $file;

        if (!class_exists($className))
        {
            throw new \RuntimeException("Class {$className} not found in " . basename($file));
        }

        $instance = new $className();
        if (!$instance instanceof Migration)
        {
            throw new \RuntimeException("{$className} must extend Core\\Migration");
        }

        return [$className, $instance];
    }

    private function classFromFilename(string $filename): string
    {
        $name = preg_replace('/^\d{8}_\d{6}_/', '', $filename);
        $name = preg_replace('/\.php$/', '', (string)$name);

        return $this->studly($name);
    }

    private function studly(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9]+/', ' ', $value);
        $value = ucwords(strtolower(trim((string)$value)));

        return str_replace(' ', '', $value);
    }

    private function appliedMap(): array
    {
        $rows = $this->pdo->query('SELECT migration, batch FROM schema_migrations ORDER BY id ASC')->fetchAll();
        $map = [];

        foreach ($rows as $row)
        {
            $map[$row['migration']] = (int)$row['batch'];
        }

        return $map;
    }

    private function nextBatchNumber(): int
    {
        $max = $this->pdo->query('SELECT MAX(batch) AS max_batch FROM schema_migrations')->fetch();
        $value = $max['max_batch'] ?? null;

        return $value ? ((int)$value + 1) : 1;
    }

    private function lastBatchNumber(): ?int
    {
        $max = $this->pdo->query('SELECT MAX(batch) AS max_batch FROM schema_migrations')->fetch();
        if (($max['max_batch'] ?? null) === null)
        {
            return null;
        }

        return (int)$max['max_batch'];
    }

    private function migrationsByBatch(int $batch): array
    {
        $statement = $this->pdo->prepare('SELECT migration FROM schema_migrations WHERE batch = :batch ORDER BY id DESC');
        $statement->execute(['batch' => $batch]);

        return $statement->fetchAll();
    }

    private function recordMigration(string $migration, int $batch): void
    {
        $statement = $this->pdo->prepare('INSERT INTO schema_migrations (migration, batch) VALUES (:migration, :batch)');
        $statement->execute([
            'migration' => $migration,
            'batch' => $batch,
        ]);
    }

    private function deleteMigrationRecord(string $migration): void
    {
        $statement = $this->pdo->prepare('DELETE FROM schema_migrations WHERE migration = :migration');
        $statement->execute(['migration' => $migration]);
    }

    private function dropAllTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table)
        {
            $name = str_replace('`', '``', (string)$table);
            $this->pdo->exec("DROP TABLE IF EXISTS `{$name}`");
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
