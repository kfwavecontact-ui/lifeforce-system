<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spot_sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            $table->string('sale_code')->unique();
            $table->string('category')->nullable();
            $table->string('sale_title');
            $table->integer('quantity')->default(1);

            $table->date('sale_date');
            $table->date('payment_due_date')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->integer('before_discount_amount')->default(0);
            $table->integer('discount_amount')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total_amount')->default(0);

            $table->string('payment_status')->default('unpaid');
            $table->text('memo')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['school_id', 'sale_date']);
            $table->index(['student_id', 'sale_date']);
            $table->index(['category', 'sale_date']);
            $table->index(['payment_status', 'sale_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_sales');
    }
};