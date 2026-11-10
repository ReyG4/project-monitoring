<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    use softDeletes;

    protected $fillable = [
        'name', 'description', 'start_date', 'target_end_date'
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_end_date' => 'date',
            'activated_at' => 'datetime',
            'status' => ProjectStatus::class,
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder{
        return match ($user->role){
            Role::ProjectManager =>$query->where('created_by', $user->id),
            Role::Tester => $query->whereIn('status', [
                ProjectStatus::Submited->value,
                ProjectStatus::Revision->value,
                ProjectStatus::Done->value,
            ]),
            default => $query->whereRaw('1 = 0'),
        };
    }
}