<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->isPostgres()) {
            DB::statement("CREATE TYPE user_role AS ENUM ('superadmin', 'library_admin', 'librarian', 'user')");
            DB::statement("ALTER TABLE users ADD COLUMN role user_role NOT NULL DEFAULT 'user'::user_role");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('user');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('jmbg', 13)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('post_code')->nullable();
            $table->string('bar_code')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'first_name',
                'last_name',
                'jmbg',
                'address',
                'city',
                'post_code',
                'bar_code',
            ]);
        });

        if ($this->isPostgres()) {
            DB::statement('ALTER TABLE users DROP COLUMN role');
            DB::statement('DROP TYPE IF EXISTS user_role');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
};
