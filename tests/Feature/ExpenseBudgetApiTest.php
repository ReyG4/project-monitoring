<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesProjectData;
use Tests\TestCase;

class ExpenseBudgetApiTest extends TestCase
{
    use RefreshDatabase, CreatesProjectData;

    private function validExpense(array $overrides = []): array
    {
        return array_merge([
            'expense_date' => '2026-11-10',
            'description' => 'Beli material',
            'amount' => 250000,
        ], $overrides);
    }

    public function test_pm_mencatat_biaya_pada_subaktivitas(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $sub = $this->makeTask($project, [], $main);
        Sanctum::actingAs($pm);

        $this->postJson("/api/tasks/{$sub->id}/expenses", $this->validExpense())
            ->assertCreated()
            ->assertJsonPath('data.recorded_by', $pm->id)
            ->assertJsonPath('data.task_id', $sub->id);
    }

    public function test_biaya_pada_aktivitas_yang_punya_subaktivitas_ditolak(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $this->makeTask($project, [], $main);
        Sanctum::actingAs($pm);

        $this->postJson("/api/tasks/{$main->id}/expenses", $this->validExpense())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task');
    }

    public function test_nominal_nol_atau_negatif_ditolak(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $task = $this->makeTask($this->makeProject($pm));
        Sanctum::actingAs($pm);

        $this->postJson("/api/tasks/{$task->id}/expenses", $this->validExpense(['amount' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');

        $this->postJson("/api/tasks/{$task->id}/expenses", $this->validExpense(['amount' => -5000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_pm_lain_tidak_bisa_mencatat_biaya(): void
    {
        $owner = $this->makeUser(Role::ProjectManager);
        $task = $this->makeTask($this->makeProject($owner));
        Sanctum::actingAs($this->makeUser(Role::ProjectManager));

        $this->postJson("/api/tasks/{$task->id}/expenses", $this->validExpense())->assertForbidden();
    }

    public function test_tester_boleh_melihat_tapi_tidak_boleh_mencatat_biaya(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm, ProjectStatus::Submitted);
        $task = $this->makeTask($project);
        $this->makeExpense($task, $pm);
        Sanctum::actingAs($this->makeUser(Role::Tester));

        $this->getJson("/api/tasks/{$task->id}/expenses")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/tasks/{$task->id}/expenses", $this->validExpense())->assertForbidden();
    }

    public function test_ringkasan_anggaran_rencana_vs_aktual(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $s1 = $this->makeTask($project, ['budget_plan' => 1000000], $main);
        $s2 = $this->makeTask($project, ['budget_plan' => 500000], $main);
        $this->makeExpense($s1, $pm, 400000);
        $this->makeExpense($s2, $pm, 100000);
        Sanctum::actingAs($pm);

        $response = $this->getJson("/api/projects/{$project->id}/budget")->assertOk();

        $this->assertEqualsWithDelta(1500000.0, $response->json('data.project.plan'), 0.001);
        $this->assertEqualsWithDelta(500000.0, $response->json('data.project.actual'), 0.001);
        $this->assertEqualsWithDelta(1000000.0, $response->json('data.project.remaining'), 0.001);
        $this->assertEqualsWithDelta(33.33, $response->json('data.project.usage_percent'), 0.001);
        $response->assertJsonCount(2, 'data.activities.0.subtasks');
    }

    public function test_biaya_melebihi_anggaran_tetap_diterima_dan_ditandai(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $task = $this->makeTask($project, ['budget_plan' => 100000]);
        Sanctum::actingAs($pm);

        $this->postJson("/api/tasks/{$task->id}/expenses", $this->validExpense(['amount' => 150000]))
            ->assertCreated();

        $this->getJson("/api/projects/{$project->id}/budget")
            ->assertOk()
            ->assertJsonPath('data.project.over_budget', true);
    }

    public function test_anggaran_nol_menghasilkan_persentase_null(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $task = $this->makeTask($project);
        $this->makeExpense($task, $pm, 50000);
        Sanctum::actingAs($pm);

        $this->getJson("/api/projects/{$project->id}/budget")
            ->assertOk()
            ->assertJsonPath('data.project.usage_percent', null);
    }

    public function test_pemilik_dapat_mengubah_dan_menghapus_biaya(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $task = $this->makeTask($this->makeProject($pm));
        $expense = $this->makeExpense($task, $pm);
        Sanctum::actingAs($pm);

        $this->putJson("/api/expenses/{$expense->id}", ['amount' => 300000])->assertOk();
        $this->assertEqualsWithDelta(300000.0, (float) $expense->fresh()->amount, 0.001);

        $this->deleteJson("/api/expenses/{$expense->id}")->assertNoContent();
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_anggaran_aktivitas_yang_punya_subaktivitas_tidak_bisa_diisi_manual(): void
    {
        $pm = $this->makeUser(Role::ProjectManager);
        $project = $this->makeProject($pm);
        $main = $this->makeTask($project);
        $this->makeTask($project, [], $main);
        Sanctum::actingAs($pm);

        $this->putJson("/api/tasks/{$main->id}", ['budget_plan' => 1000000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budget_plan');
    }
}