<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use softDeletes;

    protected $fillable = [
        'name', 'pic_name', 'weight', 'progress', 'weight', 'porgress', 'status', 'priority', 'planned_start', 'target_end', 'budget_plan', 'notes'
    ];

    protected function casts(): array{
        return[
            'weight' => 'decimal:2',
            'progress' => 'decimal:2',
            'budget_plan' => 'decimal:2',
            'planned_start' => 'date',
            'target_end' => 'date',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
        ];
    }

    public function project(): BelongsTo{
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo{
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function children(): HasMany{
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function expenses(): HasMany{
        return $this->hasMany(Expense::class);
    }

    public function isOverdue(): bool
    {
        return $this->target_end !== null 
        && $this->status !== TaskStatus::Completed 
        && $this->target_end->isBefore(today());
    }
}