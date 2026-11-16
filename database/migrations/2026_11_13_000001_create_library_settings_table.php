<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Podesavanja biblioteke su odvojena od osnovnih podataka (naziv,
        // adresa, mesto). Za sada nose samo trajanje rezervacije; kasnije se
        // ovde dodaju rokovi pozajmice, cena prekoracenja i slicno.
        Schema::create('library_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('reservation_hours')->default(48);
            $table->timestamps();
        });

        $now = now();

        DB::table('libraries')->select('id')->orderBy('id')->each(function ($library) use ($now): void {
            DB::table('library_settings')->insert([
                'library_id' => $library->id,
                'reservation_hours' => 48,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_settings');
    }
};
