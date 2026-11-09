<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time move on already-seeded databases: RolePermissionSeeder never
     * detaches, so it cannot take title approval away from Admin Prodi.
     * No-ops on a fresh install, where the seeder runs after this.
     */
    public function up(): void
    {
        $adminProdiId = DB::table('roles')->where('slug', 'admin_prodi')->value('id');
        $kaprodiId = DB::table('roles')->where('slug', 'kaprodi')->value('id');
        $editPermissionId = $this->permissionIds(['edit'])->first();

        if ($adminProdiId) {
            DB::table('permission_role')
                ->where('role_id', $adminProdiId)
                ->whereIn('permission_id', $this->permissionIds(['tambah', 'edit', 'hapus']))
                ->delete();
        }

        if ($kaprodiId && $editPermissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $kaprodiId,
                'permission_id' => $editPermissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $adminProdiId = DB::table('roles')->where('slug', 'admin_prodi')->value('id');
        $kaprodiId = DB::table('roles')->where('slug', 'kaprodi')->value('id');

        if ($kaprodiId) {
            DB::table('permission_role')
                ->where('role_id', $kaprodiId)
                ->whereIn('permission_id', $this->permissionIds(['edit']))
                ->delete();
        }

        if ($adminProdiId) {
            DB::table('permission_role')->insertOrIgnore(
                $this->permissionIds(['tambah', 'edit', 'hapus'])->map(fn (int $permissionId) => [
                    'role_id' => $adminProdiId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        }
    }

    /**
     * @param  list<string>  $actions
     */
    private function permissionIds(array $actions)
    {
        return DB::table('permissions')
            ->where('feature', 'title_submission')
            ->whereIn('action', $actions)
            ->pluck('id');
    }
};
