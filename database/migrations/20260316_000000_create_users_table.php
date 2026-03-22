<?php

use Core\Migration;
use Core\Schema;

class CreateUsersTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->createTable('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('users');
    }
}
