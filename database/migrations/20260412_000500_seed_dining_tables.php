<?php

use Core\Migration;
use Core\Schema;

class SeedDiningTables extends Migration
{
    public function up(Schema $schema): void
    {
        $rows = [
            ['name' => 'Table 2-01', 'seats' => 2, 'display_order' => 0],
            ['name' => 'Table 2-02', 'seats' => 2, 'display_order' => 1],
            ['name' => 'Table 2-03', 'seats' => 2, 'display_order' => 2],
            ['name' => 'Table 2-04', 'seats' => 2, 'display_order' => 3],
            ['name' => 'Table 2-05', 'seats' => 2, 'display_order' => 4],
            ['name' => 'Table 2-06', 'seats' => 2, 'display_order' => 5],
            ['name' => 'Table 2-07', 'seats' => 2, 'display_order' => 6],
            ['name' => 'Table 4-01', 'seats' => 4, 'display_order' => 7],
            ['name' => 'Table 4-02', 'seats' => 4, 'display_order' => 8],
            ['name' => 'Table 4-03', 'seats' => 4, 'display_order' => 9],
            ['name' => 'Table 5-01', 'seats' => 5, 'display_order' => 10],
            ['name' => 'Table 5-02', 'seats' => 5, 'display_order' => 11],
            ['name' => 'Table 6-01', 'seats' => 6, 'display_order' => 12],
            ['name' => 'Table 8-01', 'seats' => 8, 'display_order' => 13],
            ['name' => 'Table 10-01', 'seats' => 10, 'display_order' => 14],
        ];

        foreach ($rows as $row)
        {
            $name = str_replace("'", "''", (string)$row['name']);
            $seats = (int)$row['seats'];
            $displayOrder = (int)$row['display_order'];

            $schema->statement(
                "INSERT INTO `tables` (`name`, `seats`, `is_active`, `display_order`, `created_at`, `updated_at`) "
                . "SELECT '{$name}', {$seats}, 1, {$displayOrder}, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP "
                . "WHERE NOT EXISTS (SELECT 1 FROM `tables` WHERE `name` = '{$name}')"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $names = [
            'Table 2-01',
            'Table 2-02',
            'Table 2-03',
            'Table 2-04',
            'Table 2-05',
            'Table 2-06',
            'Table 2-07',
            'Table 4-01',
            'Table 4-02',
            'Table 4-03',
            'Table 5-01',
            'Table 5-02',
            'Table 6-01',
            'Table 8-01',
            'Table 10-01',
        ];

        $quoted = array_map(function (string $name): string {
            return "'" . str_replace("'", "''", $name) . "'";
        }, $names);

        $schema->statement('DELETE FROM `tables` WHERE `name` IN (' . implode(', ', $quoted) . ')');
    }
}
