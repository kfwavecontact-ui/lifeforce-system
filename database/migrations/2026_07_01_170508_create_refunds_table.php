<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->foreignId('student_id')->nullable()->constrained('students');
            $table->string('refund_code')->unique();
            $table->string('refund_source_type');
            $table->unsignedBigInteger('refund_source_id')->nullable();
            $table->integer('refund_amount');
            $table->foreignId('refund_method_id')->nullable()->constrained('payment_methods');
            $table->text('refund_reason')->nullable();
            $table->date('scheduled_date');
            $table->date('refunded_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['refund_source_type', 'refund_source_id']);
            $table->index(['scheduled_date', 'refunded_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};