<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-library sekvenca inventarnih brojeva. Atomska dodela preko
        // lockForUpdate u transakciji (analogno bar_code_seq).
        Schema::create('book_inventory_seq', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->unique()->constrained()->cascadeOnDelete();
            $table->bigInteger('last_number')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('libraries')->select('id')->orderBy('id')->each(function ($library) use ($now): void {
            DB::table('book_inventory_seq')->insert([
                'library_id' => $library->id,
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_inventory_seq');
    }
};
