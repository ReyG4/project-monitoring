<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Services\ProgressCalculator;
use Tests\TestCase;

class ProgressCalculatorTest extends TestCase
{
    private ProgressCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ProgressCalculator();
    }

    private function leaf(float $weight, float $progress): Task
    {
        $task = new Task(['weight' => $weight, 'progress' => $progress]);
        $task->setRelation('children', collect());

        return $task;
    }

    private function parentOf(float $weight, array $children): Task
    {
        $task = new Task(['weight' => $weight]);
        $task->setRelation('children', collect($children));

        return $task;
    }

    private function projectOf(array $roots): Project
    {
        $project = new Project();
        $project->setRelation('tasks', collect($roots));

        return $project;
    }

    public function test_tugas_tanpa_anak_memakai_progres_manualnya(): void
    {
        $this->assertEqualsWithDelta(
            65.0, $this->calculator->forTask($this->leaf(100, 65)), 0.001
        );
    }

    public function test_progres_aktivitas_utama_sesuai_contoh_dokumen(): void
    {
        $activity = $this->parentOf(100, [
            $this->leaf(20, 100),
            $this->leaf(50, 40),
            $this->leaf(30, 0),
        ]);

        $this->assertEqualsWithDelta(40.0, $this->calculator->forTask($activity), 0.001);
    }

    public function test_progres_proyek_sesuai_contoh_dokumen(): void
    {
        $project = $this->projectOf([
            $this->leaf(30, 50),
            $this->leaf(40, 75),
            $this->leaf(30, 20),
        ]);

        $this->assertEqualsWithDelta(51.0, $this->calculator->forProject($project), 0.001);
    }

    public function test_tiga_level_proyek_aktivitas_subaktivitas(): void
    {
        $perekrutan = $this->parentOf(40, [
            $this->leaf(30, 100),
            $this->leaf(50, 50),
            $this->leaf(20, 0),
        ]);
        $promosi = $this->parentOf(60, [
            $this->leaf(40, 100),
            $this->leaf(60, 0),
        ]);

        $project = $this->projectOf([$perekrutan, $promosi]);

        $this->assertEqualsWithDelta(55.0, $this->calculator->forTask($perekrutan), 0.001);
        $this->assertEqualsWithDelta(40.0, $this->calculator->forTask($promosi), 0.001);
        $this->assertEqualsWithDelta(46.0, $this->calculator->forProject($project), 0.001);
    }

    public function test_proyek_tanpa_tugas_berprogres_nol(): void
    {
        $this->assertEqualsWithDelta(
            0.0, $this->calculator->forProject($this->projectOf([])), 0.001
        );
    }
}