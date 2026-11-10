<?php

namespace App\Enums;

use Illuminate\Validation\Rules\InArray;

enum ProjectStatus: string{
    case Planning = 'perencanaan';
    case Active = 'berjalan';
    case Submited = 'diajukan';
    case Revision = 'revisi';
    case Done = 'Selesai';

    public function label(): string{
        return match ($this){
            self::Planning => 'Perencanaan',
            self::Active => 'Berjalan',
            self::Submited => 'Diajukan',
            self::Revision => 'Revisi',
            self::Done => 'Selesai',
        };
    }

    public function isEditable(): bool{
        return In_Array($this, [self::Planning, self::Active, self::Revision], true);
    }
}