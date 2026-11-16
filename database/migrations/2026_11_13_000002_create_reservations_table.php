<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_copy_id')->constrained('book_copies')->restrictOnDelete();

            // Rezervacija cuva slobodan primerak do `valid_till`; to je rok do
            // kog biblioteka drzi kopiju, ne rok za vracanje pozajmice.
            $table->timestamp('from_date');
            $table->timestamp('valid_till');

            // Legacy `history` flag zamenjen je eksplicitnim statusom, da
            // istorija (otkazano/isteklo/realizovano) ostane sacuvana.
            $table->string('status', 20)->default('active');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['library_id', 'status']);
            $table->index(['book_id', 'status']);
            $table->index(['book_copy_id', 'status']);
            $table->index(['status', 'valid_till']);
        });

        // Postgres i SQLite podrzavaju parcijalne indekse. Oni cuvaju dva
        // poslovna pravila i na nivou baze:
        // - jedna aktivna rezervacija po fizickoj jedinici;
        // - jedna aktivna rezervacija istog naslova za istog korisnika u
        //   istoj biblioteci.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX reservations_active_copy_unique
                 ON reservations (book_copy_id)
                 WHERE status = 'active'"
            );

            DB::statement(
                "CREATE UNIQUE INDEX reservations_active_user_book_unique
                 ON reservations (user_id, book_id, library_id)
                 WHERE status = 'active'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
