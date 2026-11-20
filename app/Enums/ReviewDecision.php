<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Pass = 'lulus';
    case Complaint = 'keluhan';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Lulus',
            self::Complaint => 'Ada keluhan',
        };
    }
}