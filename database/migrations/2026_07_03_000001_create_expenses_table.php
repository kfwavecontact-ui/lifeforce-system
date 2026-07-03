<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')
                ->constrained('schools')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('expense_code', 30)->unique();
            $table->string('expense_category', 50);
            $table->string('expense_title', 255);
            $table->string('vendor_name', 255)->nullable();

            $table->date('scheduled_date');
            $table->date('paid_at')->nullable();

            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->integer('amount')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total_amount')->default(0);

            $table->string('payment_status', 20)->default('unpaid');
            $table->text('memo')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'scheduled_date']);
            $table->index(['school_id', 'paid_at']);
            $table->index(['expense_category', 'payment_status']);
            $table->index('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
