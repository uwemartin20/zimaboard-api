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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('body');
            $table->foreignId('message_id')->nullable()->constrained('messages')->cascadeOnDelete(); // optional link to message
            $table->string('type', 50); // e.g., 'announcement', 'direct_message'
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete(); // user/admin id
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
