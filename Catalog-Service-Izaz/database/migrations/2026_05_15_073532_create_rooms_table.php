<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('ktp_number')->unique();
            $table->string('phone_number')->nullable();
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('location');
            $table->text('description')->nullable();
            $table->json('facilities')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('status')->default('AVAILABLE');
            $table->timestamps();
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('guest_id');
            $table->uuid('room_id');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->decimal('total_room_price', 12, 2);
            $table->decimal('total_addons_price', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('status')->default('LOCKED');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('guest_id')->references('id')->on('guests');
            $table->foreign('room_id')->references('id')->on('rooms');
        });

        Schema::create('booking_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('booking_id');
            $table->uuid('addon_id');
            $table->integer('quantity')->default(1);
            $table->decimal('price_at_booking', 12, 2);
            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('addon_id')->references('id')->on('addons');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_addons');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('guests');
    }
};
