<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barkod fizicke jedinice je izveden iz per-library inventarnog broja, pa
 * jedinstvenost mora biti vezana za biblioteku (kao u legacy semu gde barcode
 * nije bio globalno unique). Globalni unique je sprecavao da dve biblioteke
 * imaju kopije sa istim inventarnim brojem/barkodom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_copies', function (Blueprint $table): void {
            $table->dropUnique('book_copies_barcode_unique');
            $table->unique(['library_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('book_copies', function (Blueprint $table): void {
            $table->dropUnique(['library_id', 'barcode']);
            $table->unique('barcode');
        });
    }
};
