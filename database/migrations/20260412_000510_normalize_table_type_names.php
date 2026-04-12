<?php

use Core\Migration;
use Core\Schema;

class NormalizeTableTypeNames extends Migration
{
    public function up(Schema $schema): void
    {
        $renames = [
            'Table 2-01' => 'Small Table 01',
            'Table 2-02' => 'Small Table 02',
            'Table 2-03' => 'Small Table 03',
            'Table 2-04' => 'Small Table 04',
            'Table 2-05' => 'Small Table 05',
            'Table 2-06' => 'Small Table 06',
            'Table 2-07' => 'Small Table 07',
            'Table 4-01' => 'Medium Table 01',
            'Table 4-02' => 'Medium Table 02',
            'Table 4-03' => 'Medium Table 03',
            'Table 5-01' => 'Medium Table 04',
            'Table 5-02' => 'Medium Table 05',
            'Table 6-01' => 'Large Table 01',
            'Table 8-01' => 'Large Table 02',
            'Table 10-01' => 'Large Table 03',
        ];

        foreach ($renames as $from => $to)
        {
            $fromName = str_replace("'", "''", $from);
            $toName = str_replace("'", "''", $to);

            $schema->statement("UPDATE `tables` SET `name` = '{$toName}' WHERE `name` = '{$fromName}'");
        }

        $schema->statement("UPDATE `tables` SET `seats` = 2 WHERE `name` LIKE 'Small Table %'");
        $schema->statement("UPDATE `tables` SET `seats` = 4 WHERE `name` LIKE 'Medium Table %'");
        $schema->statement("UPDATE `tables` SET `seats` = 8 WHERE `name` LIKE 'Large Table %'");
    }

    public function down(Schema $schema): void
    {
        $renames = [
            'Small Table 01' => 'Table 2-01',
            'Small Table 02' => 'Table 2-02',
            'Small Table 03' => 'Table 2-03',
            'Small Table 04' => 'Table 2-04',
            'Small Table 05' => 'Table 2-05',
            'Small Table 06' => 'Table 2-06',
            'Small Table 07' => 'Table 2-07',
            'Medium Table 01' => 'Table 4-01',
            'Medium Table 02' => 'Table 4-02',
            'Medium Table 03' => 'Table 4-03',
            'Medium Table 04' => 'Table 5-01',
            'Medium Table 05' => 'Table 5-02',
            'Large Table 01' => 'Table 6-01',
            'Large Table 02' => 'Table 8-01',
            'Large Table 03' => 'Table 10-01',
        ];

        foreach ($renames as $from => $to)
        {
            $fromName = str_replace("'", "''", $from);
            $toName = str_replace("'", "''", $to);

            $schema->statement("UPDATE `tables` SET `name` = '{$toName}' WHERE `name` = '{$fromName}'");
        }

        $schema->statement("UPDATE `tables` SET `seats` = 2 WHERE `name` LIKE 'Table 2-%'");
        $schema->statement("UPDATE `tables` SET `seats` = 4 WHERE `name` LIKE 'Table 4-%'");
        $schema->statement("UPDATE `tables` SET `seats` = 5 WHERE `name` LIKE 'Table 5-%'");
        $schema->statement("UPDATE `tables` SET `seats` = 6 WHERE `name` = 'Table 6-01'");
        $schema->statement("UPDATE `tables` SET `seats` = 8 WHERE `name` = 'Table 8-01'");
        $schema->statement("UPDATE `tables` SET `seats` = 10 WHERE `name` = 'Table 10-01'");
    }
}
