<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->jsonb('raw_payload');
            $table->jsonb('input_overrides')->nullable();
            $table->unsignedInteger('input_version')->default(1);
            $table->jsonb('resolved_payload')->nullable();
            $table->jsonb('field_sources')->nullable();
            $table->string('isbn', 32)->nullable();
            $table->string('title')->nullable();
            $table->string('inventory_number', 32)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('failure_code', 40)->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('lookup_diagnostics')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('completed_attempts')->default(0);
            $table->string('attempt_mode', 20)->nullable();
            $table->timestamp('processing_requested_at')->nullable();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_copy_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['book_import_id', 'row_number']);
            $table->index(['book_import_id', 'status', 'row_number']);
            $table->index(['book_import_id', 'isbn']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_import_rows');
    }
};
