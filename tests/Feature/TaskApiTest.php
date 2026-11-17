<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
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

    private function makeTask(Project $project, array $attributes = [], ?Task $parent = null): Task
    {
        $task = new Task(array_merge(['name' => 'Tugas Uji'], $attributes));
        $task->project_id = $project->id;
        $task->parent_task_id = $parent?->id;
        $task->save();

        return $task;
    }

    public function test_pm_dapat_membuat_aktivitas_utama_dan_subaktivitas(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        Sanctum::actingAs($pm);

        $main = $this->postJson("/api/projects/{$project->id}/tasks", ['name' => 'Aktivitas A'])
            ->assertCreated()
            ->assertJsonPath('data.parent_task_id', null)
            ->assertJsonPath('data.status', 'not_started');

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'name' => 'Subaktivitas A1',
            'parent_task_id' => $main->json('data.id'),
        ])->assertCreated()->assertJsonPath('data.parent_task_id', $main->json('data.id'));
    }

    public function test_subaktivitas_tidak_boleh_punya_anak(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $sub = $this->makeTask($project, [], $main);
        Sanctum::actingAs($pm);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'name' => 'Cucu',
            'parent_task_id' => $sub->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_task_id');
    }

    public function test_induk_dari_proyek_lain_ditolak(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $foreign = $this->makeTask($this->makeProject($pm));
        Sanctum::actingAs($pm);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'name' => 'Salah induk',
            'parent_task_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('parent_task_id');
    }

    public function test_pm_lain_tidak_bisa_menambah_tugas(): void
    {
        $owner = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($owner);
        Sanctum::actingAs($this->makeUser(Role::ProjectManager));

        $this->postJson("/api/projects/{$project->id}/tasks", ['name' => 'X'])->assertForbidden();
    }

    public function test_daftar_tugas_menghitung_progres_berbobot(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project, ['name' => 'Aktivitas', 'weight' => 100]);
        $this->makeTask($project, ['weight' => 20, 'progress' => 100], $main);
        $this->makeTask($project, ['weight' => 50, 'progress' => 40], $main);
        $this->makeTask($project, ['weight' => 30, 'progress' => 0], $main);
        Sanctum::actingAs($pm);

        $response = $this->getJson("/api/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(3, 'data.0.children');

        $this->assertEqualsWithDelta(40.0, $response->json('data.0.progress'), 0.001);
        $this->assertEqualsWithDelta(40.0, $response->json('meta.project_progress'), 0.001);
        $this->assertEqualsWithDelta(100.0, $response->json('meta.weight_total'), 0.001);
    }

    public function test_progres_aktivitas_dengan_subaktivitas_tidak_bisa_diisi_manual(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $this->makeTask($project, [], $main);
        Sanctum::actingAs($pm);

        $this->putJson("/api/tasks/{$main->id}", ['progress' => 50])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('progress');
    }

    public function test_progres_subaktivitas_harus_antara_0_dan_100(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $sub = $this->makeTask($project, [], $main);
        Sanctum::actingAs($pm);

        $this->putJson("/api/tasks/{$sub->id}", ['progress' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('progress');

        $response = $this->putJson("/api/tasks/{$sub->id}", ['progress' => 60])->assertOk();
        $this->assertEqualsWithDelta(60.0, $response->json('data.progress'), 0.001);
    }

    public function test_terlambat_jika_target_lewat_dan_belum_selesai(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $late = $this->makeTask($project, ['target_end' => '2020-01-01', 'status' => 'in_progress']);
        $done = $this->makeTask($project, ['target_end' => '2020-01-01', 'status' => 'completed']);
        Sanctum::actingAs($pm);

        $tasks = collect($this->getJson("/api/projects/{$project->id}/tasks")->json('data'))->keyBy('id');

        $this->assertTrue($tasks[$late->id]['is_overdue']);
        $this->assertFalse($tasks[$done->id]['is_overdue']);
    }

    public function test_tester_hanya_bisa_melihat_tugas_proyek_yang_diajukan(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $submitted = $this->makeProject($pm, ProjectStatus::Submitted);
        $draft = $this->makeProject($pm);
        $task = $this->makeTask($submitted);
        Sanctum::actingAs($this->makeUser(Role::Tester));

        $this->getJson("/api/projects/{$submitted->id}/tasks")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/projects/{$draft->id}/tasks")->assertForbidden();
        $this->putJson("/api/tasks/{$task->id}", ['name' => 'X'])->assertForbidden();
    }

    public function test_hapus_aktivitas_ikut_menyembunyikan_subaktivitasnya(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $sub = $this->makeTask($project, [], $main);
        Sanctum::actingAs($pm);

        $this->deleteJson("/api/tasks/{$main->id}")->assertNoContent();

        $this->assertSoftDeleted('tasks', ['id' => $main->id]);
        $this->assertSoftDeleted('tasks', ['id' => $sub->id]);
    }

    public function test_aktivitas_yang_punya_biaya_tidak_bisa_dihapus(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $sub = $this->makeTask($project, [], $main);

        $expense = new Expense(['expense_date' => '2026-11-10', 'description' => 'Biaya uji', 'amount' => 1000]);
        $expense->task_id = $sub->id;
        $expense->recorded_by = $pm->id;
        $expense->save();

        Sanctum::actingAs($pm);

        $this->deleteJson("/api/tasks/{$main->id}")->assertStatus(409);
    }
}