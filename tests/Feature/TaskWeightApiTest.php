<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskWeightApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(Role $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function makeProject(User $owner): Project
    {
        $project = new Project(['name' => 'Proyek Uji']);
        $project->created_by = $owner->id;
        $project->status = ProjectStatus::Planning;
        $project->save();

        return $project;
    }

    private function makeTask(Project $project, ?Task $parent = null): Task
    {
        $task = new Task(['name' => 'Tugas Uji']);
        $task->project_id = $project->id;
        $task->parent_task_id = $parent?->id;
        $task->save();

        return $task;
    }

    private function payload(array $weightsById, ?int $parentId = null): array
    {
        $weights = [];

        foreach ($weightsById as $id => $weight) {
            $weights[] = ['id' => $id, 'weight' => $weight];
        }

        return ['parent_task_id' => $parentId, 'weights' => $weights];
    }

    public function test_bobot_aktivitas_utama_total_100_tersimpan(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $a = $this->makeTask($project);
        $b = $this->makeTask($project);
        Sanctum::actingAs($pm);

        $response = $this->putJson("/api/projects/{$project->id}/weights", $this->payload([$a->id => 40, $b->id => 60]))
            ->assertOk();

        $this->assertEqualsWithDelta(100.0, $response->json('meta.weight_total'), 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $a->fresh()->weight, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $b->fresh()->weight, 0.001);
    }

    public function test_total_bukan_100_ditolak_dan_tidak_menyimpan_apa_pun(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $a = $this->makeTask($project);
        $b = $this->makeTask($project);
        Sanctum::actingAs($pm);

        $this->putJson("/api/projects/{$project->id}/weights", $this->payload([$a->id => 40, $b->id => 40]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weights');

        $this->assertEqualsWithDelta(0.0, (float) $a->fresh()->weight, 0.001);
    }

    public function test_daftar_yang_kurang_dari_isi_kelompok_ditolak(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $a = $this->makeTask($project);
        $this->makeTask($project);
        Sanctum::actingAs($pm);

        $this->putJson("/api/projects/{$project->id}/weights", $this->payload([$a->id => 100]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('weights');
    }

    public function test_bobot_subaktivitas_dalam_satu_aktivitas(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $s1 = $this->makeTask($project, $main);
        $s2 = $this->makeTask($project, $main);
        $s3 = $this->makeTask($project, $main);
        Sanctum::actingAs($pm);

        $this->putJson(
            "/api/projects/{$project->id}/weights",
            $this->payload([$s1->id => 50, $s2->id => 30, $s3->id => 20], $main->id)
        )->assertOk();

        $this->assertEqualsWithDelta(30.0, (float) $s2->fresh()->weight, 0.001);
    }

    public function test_induk_harus_aktivitas_utama(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $sub = $this->makeTask($project, $main);
        Sanctum::actingAs($pm);

        $this->putJson("/api/projects/{$project->id}/weights", $this->payload([$sub->id => 100], $sub->id))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_task_id');
    }

    public function test_pecahan_desimal_yang_pas_100_diterima(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $a = $this->makeTask($project);
        $b = $this->makeTask($project);
        $c = $this->makeTask($project);
        Sanctum::actingAs($pm);

        $this->putJson(
            "/api/projects/{$project->id}/weights",
            $this->payload([$a->id => 33.33, $b->id => 33.33, $c->id => 33.34])
        )->assertOk();
    }

    public function test_pm_lain_dan_tester_tidak_bisa_mengatur_bobot(): void
    {
        $owner = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($owner);
        $a = $this->makeTask($project);
        $payload = $this->payload([$a->id => 100]);

        Sanctum::actingAs($this->makeUser(Role::ProjectManager));
        $this->putJson("/api/projects/{$project->id}/weights", $payload)->assertForbidden();

        Sanctum::actingAs($this->makeUser(Role::Tester));
        $this->putJson("/api/projects/{$project->id}/weights", $payload)->assertForbidden();
    }
}