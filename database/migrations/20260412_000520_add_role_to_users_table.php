<?php

use Core\Migration;
use Core\Schema;

class AddRoleToUsersTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->statement("ALTER TABLE `users` ADD COLUMN `role` VARCHAR(32) NOT NULL DEFAULT 'customer' AFTER `password`");
        $schema->statement("ALTER TABLE `users` ADD KEY `users_role_index` (`role`)");
        $schema->statement("UPDATE `users` SET `role` = 'staff' WHERE `role` = 'customer'");
    }

    public function down(Schema $schema): void
    {
        $schema->statement('ALTER TABLE `users` DROP INDEX `users_role_index`');
        $schema->statement('ALTER TABLE `users` DROP COLUMN `role`');
    }
}
