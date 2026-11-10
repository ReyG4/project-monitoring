<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Services\WeightValidator;
use Tests\TestCase;

class WeightValidatorTest extends TestCase
{
    private function weights(array $weights): \Illuminate\Support\Collection
    {
        return collect($weights)->map(fn ($w) => new Task(['weight' => $w]));
    }

    public function test_total_tepat_seratus_valid(): void
    {
        $this->assertTrue((new WeightValidator())->isValid($this->weights([20, 50, 30])));
    }

    public function test_total_kurang_dari_seratus_tidak_valid(): void
    {
        $this->assertFalse((new WeightValidator())->isValid($this->weights([20, 50, 20])));
    }

    public function test_total_lebih_dari_seratus_tidak_valid(): void
    {
        $this->assertFalse((new WeightValidator())->isValid($this->weights([60, 50])));
    }

    public function test_pecahan_desimal_yang_pas_seratus_tetap_valid(): void
    {
        $this->assertTrue((new WeightValidator())->isValid($this->weights([33.33, 33.33, 33.34])));
    }
}