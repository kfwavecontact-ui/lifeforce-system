<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_contents', function (Blueprint $table) {
            $table->dropForeign(['student_id']);

            $table->dropColumn([
                'content_type',
                'student_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('routine_contents', function (Blueprint $table) {
            $table->string('content_type')
                ->default('common')
                ->after('description');

            $table->foreignId('student_id')
                ->nullable()
                ->after('content_type')
                ->constrained('students')
                ->nullOnDelete();
        });
    }
};