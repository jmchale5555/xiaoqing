<?php

namespace Tests\Unit\Core;

use Core\TableBlueprint;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    public function testBlueprintGeneratesExpectedCreateSql(): void
    {
        $table = new TableBlueprint('posts');
        $table->id();
        $table->string('title', 160);
        $table->text('body')->nullable();
        $table->string('slug')->unique();
        $table->boolean('is_published')->default(false)->index();
        $table->timestamps();

        $sql = $table->toCreateSql();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `posts`', $sql);
        $this->assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringContainsString('`title` VARCHAR(160) NOT NULL', $sql);
        $this->assertStringContainsString('`body` TEXT NULL', $sql);
        $this->assertStringContainsString('UNIQUE KEY `posts_slug_unique` (`slug`)', $sql);
        $this->assertStringContainsString('KEY `posts_is_published_index` (`is_published`)', $sql);
        $this->assertStringContainsString('DEFAULT 0', $sql);
    }
}
