<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_logs');
    }
};
