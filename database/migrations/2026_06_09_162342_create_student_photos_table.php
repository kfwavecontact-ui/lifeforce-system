<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_photos', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('student_id');

            $table->string('photo_type');

            $table->string('file_path');

            $table->string('thumbnail_path')->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->boolean('is_profile')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_photos');
    }
};