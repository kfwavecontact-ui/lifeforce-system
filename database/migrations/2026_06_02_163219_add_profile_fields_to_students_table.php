<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('first_name');
            $table->date('birthday')->nullable()->after('gender');
            $table->string('school_name')->nullable()->after('grade_id');
            $table->string('commute_days')->nullable()->after('school_name');
            $table->text('remarks')->nullable()->after('commute_days');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'birthday',
                'school_name',
                'commute_days',
                'remarks',
            ]);
        });
    }
};