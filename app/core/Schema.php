<?php

namespace Core;

use PDO;

class Schema
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function statement(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function createTable(string $name, callable $callback): void
    {
        $blueprint = new TableBlueprint($name);
        $callback($blueprint);
        $this->statement($blueprint->toCreateSql());
    }

    public function dropTable(string $name): void
    {
        $name = str_replace('`', '``', $name);
        $this->statement("DROP TABLE IF EXISTS `{$name}`");
    }
}

class TableBlueprint
{
    private string $table;

    private array $columns = [];

    private array $indexes = [];

    private int $lastColumn = -1;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(string $name = 'id'): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => 'BIGINT UNSIGNED',
            'nullable' => false,
            'autoIncrement' => true,
            'primary' => true,
            'default' => null,
        ];
        $this->lastColumn = count($this->columns) - 1;

        return $this;
    }

    public function string(string $name, int $length = 255): self
    {
        return $this->addColumn($name, "VARCHAR({$length})");
    }

    public function text(string $name): self
    {
        return $this->addColumn($name, 'TEXT');
    }

    public function integer(string $name, bool $unsigned = false): self
    {
        $type = $unsigned ? 'INT UNSIGNED' : 'INT';
        return $this->addColumn($name, $type);
    }

    public function boolean(string $name): self
    {
        return $this->addColumn($name, 'TINYINT(1)');
    }

    public function timestamp(string $name): self
    {
        return $this->addColumn($name, 'TIMESTAMP');
    }

    public function timestamps(): self
    {
        $this->timestamp('created_at')->nullable()->default('CURRENT_TIMESTAMP');
        $this->timestamp('updated_at')->nullable()->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

        return $this;
    }

    public function nullable(): self
    {
        if ($this->lastColumn >= 0)
        {
            $this->columns[$this->lastColumn]['nullable'] = true;
        }

        return $this;
    }

    public function default(mixed $value): self
    {
        if ($this->lastColumn >= 0)
        {
            $this->columns[$this->lastColumn]['default'] = $value;
        }

        return $this;
    }

    public function unique(?string $name = null): self
    {
        if ($this->lastColumn < 0)
        {
            return $this;
        }

        $column = $this->columns[$this->lastColumn]['name'];
        $indexName = $name ?? "{$this->table}_{$column}_unique";

        $this->indexes[] = [
            'type' => 'UNIQUE',
            'name' => $indexName,
            'columns' => [$column],
        ];

        return $this;
    }

    public function index(?string $name = null): self
    {
        if ($this->lastColumn < 0)
        {
            return $this;
        }

        $column = $this->columns[$this->lastColumn]['name'];
        $indexName = $name ?? "{$this->table}_{$column}_index";

        $this->indexes[] = [
            'type' => 'INDEX',
            'name' => $indexName,
            'columns' => [$column],
        ];

        return $this;
    }

    public function toCreateSql(): string
    {
        $parts = [];

        foreach ($this->columns as $column)
        {
            $parts[] = $this->columnSql($column);
        }

        foreach ($this->indexes as $index)
        {
            $parts[] = $this->indexSql($index);
        }

        $table = str_replace('`', '``', $this->table);
        return "CREATE TABLE IF NOT EXISTS `{$table}` (" . implode(', ', $parts) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function addColumn(string $name, string $type): self
    {
        $this->columns[] = [
            'name' => $name,
            'type' => $type,
            'nullable' => false,
            'autoIncrement' => false,
            'primary' => false,
            'default' => null,
        ];
        $this->lastColumn = count($this->columns) - 1;

        return $this;
    }

    private function columnSql(array $column): string
    {
        $name = str_replace('`', '``', (string)$column['name']);
        $sql = "`{$name}` {$column['type']}";

        $sql .= !empty($column['nullable']) ? ' NULL' : ' NOT NULL';

        if (($column['default'] ?? null) !== null)
        {
            $sql .= ' DEFAULT ' . $this->formatDefault($column['default']);
        }

        if (!empty($column['autoIncrement']))
        {
            $sql .= ' AUTO_INCREMENT';
        }

        if (!empty($column['primary']))
        {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }

    private function indexSql(array $index): string
    {
        $name = str_replace('`', '``', (string)$index['name']);
        $columns = array_map(function (string $column): string {
            $column = str_replace('`', '``', $column);
            return "`{$column}`";
        }, $index['columns']);

        if ($index['type'] === 'UNIQUE')
        {
            return "UNIQUE KEY `{$name}` (" . implode(', ', $columns) . ')';
        }

        return "KEY `{$name}` (" . implode(', ', $columns) . ')';
    }

    private function formatDefault(mixed $value): string
    {
        if (is_int($value) || is_float($value))
        {
            return (string)$value;
        }

        if (is_bool($value))
        {
            return $value ? '1' : '0';
        }

        $str = trim((string)$value);
        if (preg_match('/^CURRENT_TIMESTAMP(\(\))?(\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP(\(\))?)?$/i', $str))
        {
            return strtoupper($str);
        }

        return "'" . str_replace("'", "''", $str) . "'";
    }
}
