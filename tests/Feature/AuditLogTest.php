<?php

namespace Tests\Feature;

use App\Enums\SupervisorApprovalStatus;
use App\Enums\ThesisStatus;
use App\Models\AuditLog;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\Thesis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()->withRoles('administrator')->create();
    }

    private function thesis(ThesisStatus $status): Thesis
    {
        $studyProgram = StudyProgram::create([
            'faculty_id' => Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT'])->id,
            'name' => 'Teknik Informatika', 'code' => 'TI',
        ]);
        $student = Student::create([
            'study_program_id' => $studyProgram->id,
            'nim' => '2201', 'name' => 'Budi', 'entry_year' => '2022', 'status' => 'aktif',
        ]);

        return $student->thesis()->create(['status' => $status]);
    }

    private function lastLogFor(string $recordType): AuditLog
    {
        return AuditLog::where('record_type', $recordType)->latest('id')->firstOrFail();
    }

    public function test_creating_a_record_is_logged_with_the_actor_and_ip(): void
    {
        $admin = User::factory()->withRoles('admin_prodi')->create();

        $this->actingAs($admin)
            ->post(route('prodi.faculties.store'), ['name' => 'Fakultas Hukum', 'code' => 'FH'], ['REMOTE_ADDR' => '10.1.2.3'])
            ->assertRedirect();

        $log = $this->lastLogFor('Faculty');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($admin->name, $log->user_name);
        $this->assertSame('Membuat', $log->action);
        $this->assertSame('Fakultas', $log->module);
        $this->assertSame('Fakultas Hukum', $log->new_values['name']);
        $this->assertSame('10.1.2.3', $log->ip_address);
    }

    public function test_an_update_records_only_the_changed_fields(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);

        $this->actingAs(User::factory()->withRoles('admin_prodi')->create())
            ->put(route('prodi.faculties.update', $faculty), ['name' => 'Fakultas Sains', 'code' => 'FT']);

        $log = $this->lastLogFor('Faculty');
        $this->assertSame('Mengubah', $log->action);
        $this->assertSame(['name' => 'Fakultas Teknik'], $log->old_values);
        $this->assertSame(['name' => 'Fakultas Sains'], $log->new_values);
    }

    public function test_a_delete_keeps_the_old_values(): void
    {
        $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);

        $this->actingAs(User::factory()->withRoles('admin_prodi')->create())
            ->delete(route('prodi.faculties.destroy', $faculty));

        $log = $this->lastLogFor('Faculty');
        $this->assertSame('Menghapus', $log->action);
        $this->assertSame('Fakultas Teknik', $log->old_values['name']);
    }

    public function test_a_status_change_is_logged_from_old_to_new(): void
    {
        $thesis = $this->thesis(ThesisStatus::Draft);
        $user = User::factory()->create();
        $this->actingAs($user);

        $thesis->transitionTo(ThesisStatus::Submitted, $user);

        $log = $this->lastLogFor('Thesis');
        $this->assertSame('Mengubah status', $log->action);
        $this->assertSame(['status' => 'DRAFT'], $log->old_values);
        $this->assertSame(['status' => 'SUBMITTED'], $log->new_values);
    }

    public function test_a_password_is_never_written_to_the_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->administrator())->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect();

        $log = AuditLog::where('record_type', 'User')->where('record_id', $user->id)->latest('id')->where('action', 'Mengubah')->firstOrFail();
        $this->assertSame('[disembunyikan]', $log->new_values['password']);
        $this->assertSame('[disembunyikan]', $log->old_values['password']);
        $this->assertStringNotContainsString($user->fresh()->password, json_encode($log->new_values));
    }

    public function test_changing_a_users_roles_is_logged_only_when_they_change(): void
    {
        $user = User::factory()->withRoles('dosen_pa')->create();
        $roles = Role::whereIn('slug', ['dosen_pa', 'dosen_penguji'])->pluck('id')->all();
        $administrator = $this->administrator();
        $payload = ['name' => $user->name, 'email' => $user->email, 'roles' => $roles];

        $this->actingAs($administrator)->put(route('users.update', $user), $payload);
        $this->actingAs($administrator)->put(route('users.update', $user), $payload);

        $logs = AuditLog::where('action', 'Mengubah role')->where('record_id', $user->id)->get();
        $this->assertCount(1, $logs);
        $this->assertSame(['dosen_pa'], $logs->first()->old_values['roles']);
        $this->assertSame(['dosen_pa', 'dosen_penguji'], $logs->first()->new_values['roles']);
    }

    public function test_changing_a_roles_permissions_is_logged(): void
    {
        $role = Role::where('slug', 'dosen_pa')->firstOrFail();
        $permission = Permission::where('name', 'lihat.history')->firstOrFail();

        $this->actingAs($this->administrator())->put(route('roles.update', $role), [
            'name' => $role->name,
            'slug' => $role->slug,
            'menu_group' => $role->menu_group,
            'permissions' => [$permission->id],
        ]);

        $log = AuditLog::where('action', 'Mengubah izin')->where('record_id', $role->id)->firstOrFail();
        $this->assertSame(['lihat.advisor_title', 'lihat.notification'], $log->old_values['permissions']);
        $this->assertSame(['lihat.history'], $log->new_values['permissions']);
    }

    public function test_the_supervisor_decision_is_logged_for_each_supervisor(): void
    {
        $thesis = $this->thesis(ThesisStatus::TitleApproved);
        foreach ([1, 2] as $position) {
            $thesis->supervisors()->create([
                'lecturer_id' => Lecturer::create(['nidn' => "00{$position}", 'name' => "Dosen {$position}", 'is_active' => true])->id,
                'position' => $position,
                'status' => SupervisorApprovalStatus::Pending,
            ]);
        }
        $kaprodi = User::factory()->withRoles('kaprodi')->create(['study_program_id' => StudyProgram::value('id')]);

        $this->actingAs($kaprodi)->post(route('prodi.supervisors.approve', $thesis))->assertRedirect();

        $logs = AuditLog::where('record_type', 'ThesisSupervisor')->where('action', 'Mengubah status')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('approved', $logs->first()->new_values['status']);
    }

    public function test_the_page_shows_entries_and_filters_by_module(): void
    {
        $administrator = $this->administrator();
        Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT']);

        $logs = $this->actingAs($administrator)->get(route('prodi.audit-log', ['module' => 'Fakultas']))
            ->assertOk()
            ->assertSee('Fakultas Teknik')
            ->viewData('logs');

        $this->assertNotEmpty($logs->items());
        $this->assertSame(['Fakultas'], collect($logs->items())->pluck('module')->unique()->values()->all());
    }

    public function test_an_invalid_date_filter_is_rejected(): void
    {
        $this->actingAs($this->administrator())
            ->get(route('prodi.audit-log', ['from' => 'bukan-tanggal']))
            ->assertSessionHasErrors('from');
    }

    public function test_only_roles_with_the_permission_can_open_the_page(): void
    {
        $this->actingAs(User::factory()->withRoles('kaprodi')->create())->get(route('prodi.audit-log'))->assertForbidden();
        $this->actingAs(User::factory()->withRoles('admin_prodi')->create())->get(route('prodi.audit-log'))->assertForbidden();
    }
}
