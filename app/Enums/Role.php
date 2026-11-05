<?php

namespace App\Enums;

enum Role: string{
    case ProjectManager = 'project_manager';
    case Tester = 'tester';
    case Operator = 'operator';

    public function label(): string{
        return match($this){
            self::ProjectManager => 'Project Manager',
            self::Tester => 'Tester',
            self::Operator => 'Operator',
        };
    }
}