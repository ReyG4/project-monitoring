<?php

namespace App\Enums;

enum Role: String{
    case ProjectManager = 'Project Manager';
    case Tester = 'Tester';
    case Operator = 'Operator';
}