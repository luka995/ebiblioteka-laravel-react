<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_user', function (Blueprint $table): void {
            $table->string('role', 20)->default('user');
        });

        // Postojece globalne role prebacujemo na clanstvo. Superadmin je
        // globalna privilegija (settings), pa njegovo clanstvo dobija
        // library_admin kako bi zadrzalo pun pristup ako se privilegija ukine.
        DB::table('users')
            ->select(['id', 'role'])
            ->orderBy('id')
            ->each(function ($user): void {
                $role = $user->role === 'superadmin' ? 'library_admin' : $user->role;

                DB::table('library_user')
                    ->where('user_id', $user->id)
                    ->update(['role' => $role]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TYPE IF EXISTS user_role');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE TYPE user_role AS ENUM ('superadmin', 'library_admin', 'librarian', 'user')");
            DB::statement("ALTER TABLE users ADD COLUMN role user_role NOT NULL DEFAULT 'user'::user_role");
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role')->default('user');
            });
        }

        $superAdminIds = DB::table('settings')
            ->where('key', 'super_admin_user_ids')
            ->value('value');

        $superAdminIds = $superAdminIds === null
            ? []
            : array_map('intval', (array) json_decode($superAdminIds, true));

        DB::table('users')->select('id')->orderBy('id')->each(function ($user) use ($superAdminIds): void {
            if (in_array((int) $user->id, $superAdminIds, true)) {
                $role = 'superadmin';
            } else {
                $role = DB::table('library_user')
                    ->where('user_id', $user->id)
                    ->orderByRaw("CASE role WHEN 'library_admin' THEN 3 WHEN 'librarian' THEN 2 ELSE 1 END DESC")
                    ->value('role') ?? 'user';
            }

            DB::table('users')->where('id', $user->id)->update(['role' => $role]);
        });

        Schema::table('library_user', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
