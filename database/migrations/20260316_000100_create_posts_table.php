<?php

use Core\Migration;
use Core\Schema;

class CreatePostsTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->createTable('posts', function ($table) {
            $table->id();
            $table->integer('user_id', true)->index();
            $table->string('title', 160);
            $table->text('body');
            $table->string('slug', 180)->unique();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('posts');
    }
}
