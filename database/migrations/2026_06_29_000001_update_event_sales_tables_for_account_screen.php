<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('event_applications', 'school_id')) {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('event_schedule_id')
                    ->constrained('schools')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('event_applications', 'event_price_id')) {
                $table->foreignId('event_price_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('event_prices')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('event_applications', 'cancelled_reason')) {
                $table->text('cancelled_reason')->nullable()->after('cancelled_at');
            }

            if (! Schema::hasColumn('event_applications', 'memo')) {
                $table->text('memo')->nullable()->after('cancelled_reason');
            }
        });

        Schema::table('event_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('event_payments', 'account_transaction_id')) {
                $table->foreignId('account_transaction_id')
                    ->nullable()
                    ->after('event_application_id')
                    ->constrained('account_transactions')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->unique('account_transaction_id', 'event_payments_account_transaction_id_unique');
            }

            if (! Schema::hasColumn('event_payments', 'payment_method_id')) {
                $table->foreignId('payment_method_id')
                    ->nullable()
                    ->after('account_transaction_id')
                    ->constrained('payment_methods')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('event_payments', 'scheduled_payment_date')) {
                $table->date('scheduled_payment_date')->nullable()->after('payment_method_id');
            }

            if (! Schema::hasColumn('event_payments', 'memo')) {
                $table->text('memo')->nullable()->after('raw_response');
            }
        });

        Schema::table('event_refunds', function (Blueprint $table) {
            if (! Schema::hasColumn('event_refunds', 'event_application_id')) {
                $table->foreignId('event_application_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('event_applications')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('event_refunds', 'account_transaction_id')) {
                $table->foreignId('account_transaction_id')
                    ->nullable()
                    ->after('event_payment_id')
                    ->constrained('account_transactions')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->unique('account_transaction_id', 'event_refunds_account_transaction_id_unique');
            }

            if (! Schema::hasColumn('event_refunds', 'refund_method_id')) {
                $table->foreignId('refund_method_id')
                    ->nullable()
                    ->after('account_transaction_id')
                    ->constrained('payment_methods')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('event_refunds', 'scheduled_refund_date')) {
                $table->date('scheduled_refund_date')->nullable()->after('refund_method_id');
            }

            if (! Schema::hasColumn('event_refunds', 'memo')) {
                $table->text('memo')->nullable()->after('raw_response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_refunds', function (Blueprint $table) {
            if (Schema::hasColumn('event_refunds', 'account_transaction_id')) {
                $table->dropUnique('event_refunds_account_transaction_id_unique');
                $table->dropConstrainedForeignId('account_transaction_id');
            }

            foreach (['event_application_id', 'refund_method_id'] as $column) {
                if (Schema::hasColumn('event_refunds', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['scheduled_refund_date', 'memo'] as $column) {
                if (Schema::hasColumn('event_refunds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('event_payments', function (Blueprint $table) {
            if (Schema::hasColumn('event_payments', 'account_transaction_id')) {
                $table->dropUnique('event_payments_account_transaction_id_unique');
                $table->dropConstrainedForeignId('account_transaction_id');
            }

            if (Schema::hasColumn('event_payments', 'payment_method_id')) {
                $table->dropConstrainedForeignId('payment_method_id');
            }

            foreach (['scheduled_payment_date', 'memo'] as $column) {
                if (Schema::hasColumn('event_payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('event_applications', function (Blueprint $table) {
            foreach (['school_id', 'event_price_id'] as $column) {
                if (Schema::hasColumn('event_applications', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['cancelled_reason', 'memo'] as $column) {
                if (Schema::hasColumn('event_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
