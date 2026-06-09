<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_documents', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('student_id');

            $table->string('document_type');

            $table->string('file_name');

            $table->string('file_path');

            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};