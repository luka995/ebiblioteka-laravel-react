<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('school_name', 200);
            $table->string('school_type', 40);
            $table->string('city', 120);
            $table->string('address', 200);
            $table->string('contact_name', 120);
            $table->string('contact_email');
            $table->string('contact_phone', 50);
            $table->unsignedInteger('student_count')->nullable();
            $table->unsignedInteger('employee_count')->nullable();
            $table->text('message')->nullable();

            // pending -> approved | rejected
            $table->string('status', 20)->default('pending');
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_requests');
    }
};
