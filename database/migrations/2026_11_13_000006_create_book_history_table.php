<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit trag za cirkulaciju knjiga. Naziv i osnovna
        // polja prate legacy `book_history`, uz `actor_id` (ko je izvrsio
        // akciju) i `meta` za dodatne podatke (rok rezervacije i slicno).
        Schema::create('book_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->timestamp('action_date')->useCurrent();
            $table->json('meta')->nullable();

            $table->index(['library_id', 'action_date']);
            $table->index(['book_id', 'action_date']);
            $table->index(['book_copy_id', 'action_date']);
            $table->index(['user_id', 'action_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_history');
    }
};
