<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcode_print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('library_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // library = brza stampa celog fonda; list = serverska lista za
            // stampu (PRD §14, dolazi u sledecoj fazi).
            $table->string('scope', 20)->default('library');

            // label (62x29mm) | a4 (4x12 = 48 nalepnica).
            $table->string('format', 10);

            // pending -> processing -> completed | failed
            $table->string('status', 20)->default('pending');

            // Putanja na privatnom `barcode` disku (nikada javno dostupna).
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('items_count')->nullable();

            // Jedinice preskocene zbog neispravnog EAN-13 barkoda.
            $table->unsignedInteger('invalid_count')->default(0);

            $table->text('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // Lista poslova za biblioteku + brza detekcija statusa pri pollingu.
            $table->index(['library_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcode_print_jobs');
    }
};
