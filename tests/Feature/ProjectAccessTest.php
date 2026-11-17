<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function makeProject(User $owner, ProjectStatus $status = ProjectStatus::Planning): Project
    {
        $project = new Project(['name' => 'Proyek Uji']);
        $project->created_by = $owner->id;
        $project->status = $status;
        $project->save();

        return $project;
    }

    public function test_project_manager_dapat_membuat_proyek(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        Sanctum::actingAs($pm);

        $this->postJson('/api/projects', ['name' => 'Proyek A'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'perencanaan');

        $this->assertDatabaseHas('projects', ['name' => 'Proyek A', 'created_by' => $pm->id]);
    }

    public function test_project_manager_hanya_melihat_proyek_miliknya(): void
    {
        $pm1 = $this->makeUser(Role::ProjectManager);
        $pm2 = $this->makeUser(Role::ProjectManager);
        $mine = $this->makeProject($pm1);
        $this->makeProject($pm2);

        Sanctum::actingAs($pm1);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_project_manager_tidak_bisa_membuka_proyek_pm_lain(): void
    {
        $pm1 = $this->makeUser(Role::ProjectManager);
        $pm2 = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm1);

        Sanctum::actingAs($pm2);

        $this->getJson("/api/projects/{$project->id}")->assertForbidden();
    }

    public function test_status_tidak_bisa_diubah_lewat_data_kiriman(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);

        Sanctum::actingAs($pm);

        $this->putJson("/api/projects/{$project->id}", ['name' => 'Nama baru', 'status' => 'selesai'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama baru')
            ->assertJsonPath('data.status', 'perencanaan');

        $this->assertSame(ProjectStatus::Planning, $project->fresh()->status);
    }

    public function test_tester_tidak_bisa_membuat_proyek(): void
    {
        Sanctum::actingAs($this->makeUser(Role::Tester));

        $this->postJson('/api/projects', [])->assertForbidden();
    }

    public function test_tester_hanya_melihat_proyek_yang_sudah_diajukan(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $this->makeProject($pm, ProjectStatus::Planning);
        $submitted = $this->makeProject($pm, ProjectStatus::Submitted);

        Sanctum::actingAs($this->makeUser(Role::Tester));

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $submitted->id);
    }

    public function test_operator_ditolak_melihat_daftar_proyek(): void
    {
        Sanctum::actingAs($this->makeUser(Role::Operator));

        $this->getJson('/api/projects')->assertForbidden();
    }

    public function test_nama_proyek_wajib_diisi(): void
    {
        Sanctum::actingAs($this->makeUser(Role::ProjectManager));

        $this->postJson('/api/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_tanggal_target_tidak_boleh_sebelum_tanggal_mulai(): void
    {
        Sanctum::actingAs($this->makeUser(Role::ProjectManager));

        $this->postJson('/api/projects', [
            'name' => 'X',
            'start_date' => '2026-12-10',
            'target_end_date' => '2026-12-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('target_end_date');
    }

    public function test_permintaan_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
    }

    public function test_hapus_proyek_bersifat_lunak_lalu_tidak_ditemukan(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);

        Sanctum::actingAs($pm);

        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
    }

    public function test_proyek_yang_sedang_diajukan_tidak_bisa_diubah(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm, ProjectStatus::Submitted);

        Sanctum::actingAs($pm);

        $this->putJson("/api/projects/{$project->id}", ['name' => 'Ubah'])->assertForbidden();
    }
}