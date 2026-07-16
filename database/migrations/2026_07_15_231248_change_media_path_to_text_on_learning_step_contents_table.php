<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE learning_step_contents
            ALTER COLUMN media_path TYPE text
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE learning_step_contents
            ALTER COLUMN media_path TYPE varchar(255)
        ');
    }
};