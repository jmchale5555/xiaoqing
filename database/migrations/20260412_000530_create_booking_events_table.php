<?php

use Core\Migration;
use Core\Schema;

class CreateBookingEventsTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->createTable('booking_events', function ($table) {
            $table->id();
            $table->integer('booking_id', true)->index();
            $table->string('event_type', 64)->index();
            $table->integer('actor_user_id', true)->nullable()->index();
            $table->text('from_value')->nullable();
            $table->text('to_value')->nullable();
            $table->text('meta_json')->nullable();
            $table->timestamp('created_at')->default('CURRENT_TIMESTAMP')->index();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('booking_events');
    }
}
