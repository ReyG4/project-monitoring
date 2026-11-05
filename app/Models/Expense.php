<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use softDeletes;
    protected $fillable = [
        'expense_date', 'description', 'amount'
    ];

    protected function casts(): array{
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function task(): BelongsTo{
        return $this->belongsTo(Task::class);
    }
}