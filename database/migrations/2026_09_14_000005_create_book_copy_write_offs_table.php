<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copy_write_offs', function (Blueprint $table): void {
            $table->id();

            // Snapshot polja cuvaju reviziju i posle trajnog brisanja kopije.
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->foreignId('book_id')->nullable()->constrained('books')->nullOnDelete();
            $table->string('order_number', 32)->nullable();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();

            $table->string('reason');
            $table->date('occurred_at')->nullable();
            $table->text('notice')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['library_id', 'occurred_at']);
            $table->index('book_copy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copy_write_offs');
    }
};
