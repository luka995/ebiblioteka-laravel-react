<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // pending -> processing -> completed | failed
            $table->string('status', 20)->default('pending');

            // Putanja na privatnom `inventory` disku (nikada javno dostupna).
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('rows_count')->nullable();
            $table->string('locale', 10)->default('sr-Cyrl');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            // Lista za biblioteku + brza detekcija statusa pri pollingu.
            $table->index(['library_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_books');
    }
};
