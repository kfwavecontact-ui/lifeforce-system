<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();

            $table->date('scheduled_date')->nullable();
            $table->date('transaction_date')->nullable();

            $table->foreignId('account_category_id')
                ->constrained('account_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('transaction_name', 255);

            $table->integer('amount');

            // 割引情報
            $table->integer('before_discount_amount')->nullable();
            $table->integer('discount_amount')->nullable();

            $table->foreignId('discount_type_id')
                ->nullable()
                ->constrained('discounts')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->text('discount_note')->nullable();

            $table->string('status', 50)->default('planned'); // planned / confirmed / cancelled

            $table->text('cancelled_reason')->nullable();
            $table->text('memo')->nullable();

            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('source_table', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('related_transaction_id')
                ->nullable()
                ->constrained('account_transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'scheduled_date']);
            $table->index(['school_id', 'transaction_date']);
            $table->index(['account_category_id', 'status']);
            $table->index(['source_table', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};