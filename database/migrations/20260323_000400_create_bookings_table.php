<?php

use Core\Migration;
use Core\Schema;

class CreateBookingsTable extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->createTable('bookings', function ($table) {
            $table->id();
            $table->string('guest_name', 160);
            $table->string('guest_phone', 40)->nullable();
            $table->string('guest_email', 180)->nullable();
            $table->integer('party_size', true)->default(2)->index();
            $table->timestamp('booking_start')->index();
            $table->timestamp('booking_end')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->integer('table_id', true)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('bookings');
    }
}
