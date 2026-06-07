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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_type_id');
            $table->unsignedBigInteger('contact_status_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('title');
            $table->string('priority');
            $table->string('related_table');
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
