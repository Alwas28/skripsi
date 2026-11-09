<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // User::factory(10)->create();

        // updateOrCreate (not factory()->create()) so re-running `db:seed`
        // is safe and just fixes up roles instead of failing on the unique
        // email constraint.
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')],
        );

        // Demo accounts so each sidebar can actually be logged into and
        // clicked through; "dosen" shows one account holding several roles.
        // Password for all of them: "password".
        foreach ([
            'mahasiswa' => ['Mahasiswa Demo', ['mahasiswa']],
            'dosen' => ['Dosen Demo', Role::DOSEN_ROLES],
            'admin_prodi' => ['Admin Prodi Demo', ['admin_prodi']],
            'kaprodi' => ['Kaprodi Demo', ['kaprodi']],
        ] as $emailName => [$name, $roleSlugs]) {
            User::updateOrCreate(
                ['email' => "{$emailName}@umkendari.ac.id"],
                ['name' => $name, 'password' => bcrypt('password')],
            )->roles()->sync(Role::whereIn('slug', $roleSlugs)->pluck('id'));
        }
    }
}
