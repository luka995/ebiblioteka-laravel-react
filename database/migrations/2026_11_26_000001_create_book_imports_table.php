<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('sheet_name')->nullable();
            $table->string('status', 30)->default('pending');
            $table->boolean('has_inventory_column')->default(false);
            $table->jsonb('headers')->nullable();
            $table->jsonb('column_map')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedBigInteger('completed_attempts')->default(0);
            $table->uuid('processing_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->string('failure_code', 40)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['library_id', 'status']);
            $table->index(['library_id', 'created_at']);
            $table->index('lease_expires_at');
        });

        DB::statement(
            "CREATE UNIQUE INDEX book_imports_one_active_per_library
             ON book_imports (library_id)
             WHERE status IN ('pending', 'ready', 'processing')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('book_imports');
    }
};
