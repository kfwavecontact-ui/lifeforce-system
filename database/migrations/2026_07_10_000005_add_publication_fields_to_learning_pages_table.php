<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_pages', 'publication_status')) {
                $table->string('publication_status', 20)->default('unpublished')->after('status');
            }
            if (! Schema::hasColumn('learning_pages', 'publish_start_at')) {
                $table->timestamp('publish_start_at')->nullable()->after('publication_status');
            }
            if (! Schema::hasColumn('learning_pages', 'publish_end_at')) {
                $table->timestamp('publish_end_at')->nullable()->after('publish_start_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('learning_pages', function (Blueprint $table) {
            $columns = [];
            foreach (['publication_status', 'publish_start_at', 'publish_end_at'] as $column) {
                if (Schema::hasColumn('learning_pages', $column)) {
                    $columns[] = $column;
                }
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
