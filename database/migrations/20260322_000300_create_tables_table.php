<?php

use Core\Migration;
use Core\Schema;

class CreateTablesTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->createTable('tables', function ($table) {
            $table->id();
            $table->string('name', 120)->index();
            $table->integer('seats', true)->default(2);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('display_order', true)->default(0)->index();
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tables');
    }
}
