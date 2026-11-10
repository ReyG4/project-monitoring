<?php

namespace App\Services;

final class BudgetSummary
{
    /**
     * Create a new class instance.
     */

    public readonly float $plan;
    public readonly float $actual;

    public function __construct(float $plan, float $actual)
    {
        $this->plan = round($plan, 2);
        $this->actual = round($actual, 2);
    }

    public function remaining(): float{
        return round($this->plan - $this->actual, 2);
    }

    public function usagePercent(): ?float{
        if($this->plan <= 0){
            return null;
        }
        return round($this->actual / $this->plan * 100, 2);
    }

    public function isOverBudget(): bool{
        return $this->actual > $this->plan;
    }
}