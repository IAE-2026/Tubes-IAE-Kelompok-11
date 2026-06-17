<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_addon', function (Blueprint $table) {
            $table->id();
            $table->uuid('room_id');
            $table->uuid('addon_id');

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('cascade');
            $table->foreign('addon_id')->references('id')->on('addons')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_addon');
    }
};
