<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUpdatedByToAccountTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('account_transactions', 'updated_by')) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('account_transactions', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
        });
    }
}