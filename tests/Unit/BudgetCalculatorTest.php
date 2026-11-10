<?php

namespace Tests\Unit;

use App\Models\Expense;
use App\Models\Project;
use App\Models\Task;
use App\Services\BudgetCalculator;
use Tests\TestCase;

class BudgetCalculatorTest extends TestCase
{
    private BudgetCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BudgetCalculator();
    }

    private function leaf(float $plan, array $expenses = []): Task
    {
        $task = new Task(['budget_plan' => $plan]);
        $task->setRelation('children', collect());
        $task->setRelation('expenses', collect(array_map(
            fn ($amount) => new Expense(['amount' => $amount]),
            $expenses
        )));

        return $task;
    }

    private function parentOf(array $children): Task
    {
        $task = new Task();
        $task->setRelation('children', collect($children));
        $task->setRelation('expenses', collect());

        return $task;
    }

    private function projectOf(array $roots): Project
    {
        $project = new Project();
        $project->setRelation('tasks', collect($roots));

        return $project;
    }

    public function test_sisa_dan_persentase_dihitung_dari_plan_dan_biaya(): void
    {
        $summary = $this->calculator->forTask($this->leaf(1000000, [300000, 200000]));

        $this->assertEqualsWithDelta(500000.0, $summary->actual, 0.001);
        $this->assertEqualsWithDelta(500000.0, $summary->remaining(), 0.001);
        $this->assertEqualsWithDelta(50.0, $summary->usagePercent(), 0.001);
        $this->assertFalse($summary->isOverBudget());
    }

    public function test_biaya_melebihi_plan_tetap_dihitung(): void
    {
        $summary = $this->calculator->forTask($this->leaf(100000, [150000]));

        $this->assertEqualsWithDelta(-50000.0, $summary->remaining(), 0.001);
        $this->assertEqualsWithDelta(150.0, $summary->usagePercent(), 0.001);
        $this->assertTrue($summary->isOverBudget());
    }

    public function test_plan_nol_menghasilkan_persentase_null(): void
    {
        $summary = $this->calculator->forTask($this->leaf(0, [50000]));

        $this->assertNull($summary->usagePercent());
    }

    public function test_angka_milik_induk_sendiri_tidak_dihitung_dua_kali(): void
    {
        $parent = new Task(['budget_plan' => 999999]);
        $parent->setRelation('children', collect([
            $this->leaf(100000, [30000]),
            $this->leaf(200000, [50000]),
        ]));
        $parent->setRelation('expenses', collect([new Expense(['amount' => 777])]));

        $summary = $this->calculator->forTask($parent);

        $this->assertEqualsWithDelta(300000.0, $summary->plan, 0.001);
        $this->assertEqualsWithDelta(80000.0, $summary->actual, 0.001);
    }

    public function test_rekap_tiga_level_sampai_proyek(): void
    {
        $aktivitasA = $this->parentOf([
            $this->leaf(1000000, [400000]),
            $this->leaf(500000, [100000]),
        ]);
        $aktivitasB = $this->parentOf([
            $this->leaf(500000, [600000]),
        ]);
        $project = $this->projectOf([$aktivitasA, $aktivitasB]);

        $a = $this->calculator->forTask($aktivitasA);
        $b = $this->calculator->forTask($aktivitasB);
        $total = $this->calculator->forProject($project);

        $this->assertEqualsWithDelta(33.33, $a->usagePercent(), 0.001);
        $this->assertTrue($b->isOverBudget());
        $this->assertEqualsWithDelta(2000000.0, $total->plan, 0.001);
        $this->assertEqualsWithDelta(1100000.0, $total->actual, 0.001);
        $this->assertEqualsWithDelta(900000.0, $total->remaining(), 0.001);
        $this->assertEqualsWithDelta(55.0, $total->usagePercent(), 0.001);
    }

    public function test_proyek_tanpa_tugas_tidak_menghasilkan_error(): void
    {
        $summary = $this->calculator->forProject($this->projectOf([]));

        $this->assertEqualsWithDelta(0.0, $summary->plan, 0.001);
        $this->assertNull($summary->usagePercent());
    }
}