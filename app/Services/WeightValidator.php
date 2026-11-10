<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Collection;

class WeightValidator
{
    /**
     * Create a new class instance.
     */

    public const TOTAL = 100.0;
    public const TOLERANCE = 0.005;

    public function total(Collection $siblings): float{
        return round( (float) $siblings->sum(
            fn (Task $task) => (float)$task->weight
        ), 2);
    }

    public function isValid(Collection $siblings): bool{
        if ($siblings->isEmpty()){
            return true;
        }
        return abs($this->total($siblings) - self::TOTAL) < self::TOLERANCE;
    }

    public function __construct()
    {
        //
    }
}