<?php

use Core\Migration;
use Core\Schema;

class CreateMenuItemsTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->createTable('menu_items', function ($table) {
            $table->id();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->integer('price_pence', true)->default(0);
            $table->string('category', 120)->nullable()->index();
            $table->string('image_path', 255)->nullable();
            $table->integer('display_order', true)->default(0)->index();
            $table->boolean('is_available')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('menu_items');
    }
}
