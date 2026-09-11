<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL SEQUENCE je alternativa, ali legacy tabela olaksava
        // kasniju migraciju podataka iz postojece aplikacije.
        Schema::create('bar_code_seq', function (Blueprint $table): void {
            // Keep the legacy shape for a straightforward future data migration.
            $table->bigInteger('code');
        });

        DB::table('bar_code_seq')->insert(['code' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bar_code_seq');
    }
};
