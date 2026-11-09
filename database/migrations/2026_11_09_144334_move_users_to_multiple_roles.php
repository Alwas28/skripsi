<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DOSEN_ROLES = [
        'dosen_pa' => 'Dosen PA',
        'dosen_pembimbing' => 'Dosen Pembimbing',
        'dosen_penguji' => 'Dosen Penguji',
    ];

    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'user_id']);
        });

        DB::table('role_user')->insertUsing(
            ['role_id', 'user_id', 'created_at', 'updated_at'],
            DB::table('users')->whereNotNull('role_id')->select('role_id', 'id', 'created_at', 'updated_at')
        );

        $this->splitDosenRole();
        $this->removeAdvisorMenuFromKaprodi();

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
    }

    /**
     * Rollback keeps one role per user and does not bring the old combined
     * `dosen` role back.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained()->nullOnDelete();
        });

        DB::table('role_user')->orderBy('id')->get()->unique('user_id')->each(
            fn (object $assignment) => DB::table('users')->where('id', $assignment->user_id)->update(['role_id' => $assignment->role_id])
        );

        Schema::dropIfExists('role_user');
    }

    /**
     * Accounts on the old `dosen` role get all three new roles, so nobody
     * loses access. No-ops on a fresh install, where the seeder creates them.
     */
    private function splitDosenRole(): void
    {
        $dosenRoleId = DB::table('roles')->where('slug', 'dosen')->value('id');

        if (! $dosenRoleId) {
            return;
        }

        $newRoleIds = collect(self::DOSEN_ROLES)->map(function (string $name, string $slug) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'menu_group' => 'dosen', 'is_super_admin' => false, 'created_at' => now(), 'updated_at' => now()],
            );

            return DB::table('roles')->where('slug', $slug)->value('id');
        });

        DB::table('role_user')->where('role_id', $dosenRoleId)->pluck('user_id')->each(
            fn (int $userId) => DB::table('role_user')->insertOrIgnore($newRoleIds->map(fn (int $roleId) => [
                'role_id' => $roleId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()->all())
        );

        DB::table('roles')->where('id', $dosenRoleId)->delete();
    }

    /**
     * A Kaprodi who is also a PA now simply holds the Dosen PA role as well.
     */
    private function removeAdvisorMenuFromKaprodi(): void
    {
        $kaprodiRoleId = DB::table('roles')->where('slug', 'kaprodi')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'lihat.advisor_title')->value('id');

        if ($kaprodiRoleId && $permissionId) {
            DB::table('permission_role')->where('role_id', $kaprodiRoleId)->where('permission_id', $permissionId)->delete();
        }
    }
};
