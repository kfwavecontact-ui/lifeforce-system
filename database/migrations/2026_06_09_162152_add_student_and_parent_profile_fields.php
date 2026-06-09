<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {

            $table->text('medical_notes')
                ->nullable()
                ->after('remarks');

            $table->string('admission_source')
                ->nullable()
                ->after('medical_notes');

        });

        Schema::table('parents', function (Blueprint $table) {

            $table->boolean('is_emergency_contact')
                ->default(false)
                ->after('occupation');

            $table->integer('emergency_priority')
                ->default(0)
                ->after('is_emergency_contact');

        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {

            $table->dropColumn([
                'medical_notes',
                'admission_source',
            ]);

        });

        Schema::table('parents', function (Blueprint $table) {

            $table->dropColumn([
                'is_emergency_contact',
                'emergency_priority',
            ]);

        });
    }
};