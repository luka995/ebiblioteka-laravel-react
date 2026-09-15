<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_copies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();

            // Inventarni broj i redni broj su jedinstveni u okviru biblioteke.
            // Unique ograde ukljucuju i soft-deleted (arhivirane) kopije: arhiva
            // zadrzava svoj broj dok se ne obrise trajno.
            $table->string('order_number', 32)->nullable();
            $table->integer('seq_number')->nullable();
            $table->string('barcode', 13)->nullable();

            $table->string('isbn', 32)->nullable();
            $table->string('publisher')->nullable();
            $table->string('publish_place')->nullable();
            $table->string('publish_year', 16)->nullable();
            $table->string('issue_number')->nullable();
            $table->integer('num_of_pages')->nullable();
            $table->string('dimension')->nullable();
            $table->string('part')->nullable();
            $table->string('udk')->nullable();
            $table->string('binding')->nullable();
            $table->string('origin')->nullable();
            $table->string('book_number')->nullable();
            $table->string('place_on_shelf')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->date('date_add')->nullable();
            $table->text('notice')->nullable();

            // Operativno stanje (cirkulacija dolazi u narednim fazama).
            $table->boolean('borrowed')->default(false);
            $table->boolean('reserved')->default(false);

            // Napomena "pogresno zavedena" (nije otpis, ostaje na kopiji).
            $table->boolean('rec_error')->default(false);
            $table->string('rec_error_notice')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['library_id', 'order_number']);
            $table->unique(['library_id', 'seq_number']);
            $table->unique('barcode');
            $table->index(['library_id', 'book_id']);
            $table->index('isbn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_copies');
    }
};
