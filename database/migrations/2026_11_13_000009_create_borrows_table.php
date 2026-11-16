<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_id')->constrained()->restrictOnDelete();
            $table->foreignId('book_copy_id')->constrained('book_copies')->restrictOnDelete();

            // Ako je pozajmica realizovana iz rezervacije, cuvamo vezu na nju.
            // Unique sprecava da se ista rezervacija realizuje dva puta.
            $table->foreignId('reservation_id')->nullable()->unique()->constrained('reservations')->nullOnDelete();

            // Osoblje koje je evidentiralo izdavanje.
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('from_date');
            $table->timestamp('valid_till');
            $table->timestamp('return_date')->nullable();
            $table->boolean('returned')->default(false);

            // Konacni obracun prekoracenja u trenutku razduzenja.
            $table->unsignedInteger('overdue_days')->default(0);
            $table->decimal('overdue_amount', 10, 2)->default(0);

            $table->timestamps();

            $table->index(['user_id', 'returned']);
            $table->index(['library_id', 'returned']);
            $table->index('book_id');
            $table->index(['book_copy_id', 'returned']);
            $table->index(['returned', 'valid_till']);
        });

        // Jedna fizicka jedinica moze imati samo jednu aktivnu pozajmicu.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX borrows_active_copy_unique
                 ON borrows (book_copy_id)
                 WHERE returned = false'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('borrows');
    }
};
