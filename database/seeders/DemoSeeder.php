<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command->error('DemoSeeder hanya boleh dijalankan di lingkungan lokal.');

            return;
        }

        $accounts = [
            ['Operator Demo', 'operator@demo.test', Role::Operator],
            ['PM Demo', 'pm@demo.test', Role::ProjectManager],
            ['Tester Demo', 'tester@demo.test', Role::Tester],
        ];

        $users = [];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::where('email', $email)->first()
                ?? new User(['name' => $name, 'email' => $email, 'password' => 'PasswordDemo123!']);
            $user->role = $role;
            $user->save();
            $users[$role->value] = $user;
        }

        if (Project::where('name', 'Proyek Demo (Berjalan)')->exists()) {
            return;
        }

        $make = function (Project $project, string $name, array $attributes = [], ?Task $parent = null): Task {
            $task = new Task(array_merge(['name' => $name], $attributes));
            $task->project_id = $project->id;
            $task->parent_task_id = $parent?->id;
            $task->save();

            return $task;
        };

        $pm = $users[Role::ProjectManager->value];

        $active = new Project(['name' => 'Proyek Demo (Berjalan)', 'description' => 'Data contoh untuk pengembangan frontend.']);
        $active->created_by = $pm->id;
        $active->status = ProjectStatus::Active;
        $active->save();

        $a = $make($active, 'Aktivitas A', ['weight' => 60]);
        $make($active, 'Sub A1', ['weight' => 50, 'progress' => 100, 'status' => 'completed', 'budget_plan' => 1000000, 'pic_name' => 'Budi'], $a);
        $make($active, 'Sub A2', ['weight' => 30, 'progress' => 40, 'status' => 'in_progress', 'budget_plan' => 500000, 'target_end' => '2026-11-01', 'pic_name' => 'Sari'], $a);
        $make($active, 'Sub A3', ['weight' => 20, 'progress' => 0, 'budget_plan' => 250000], $a);

        $b = $make($active, 'Aktivitas B', ['weight' => 40]);
        $make($active, 'Sub B1', ['weight' => 100, 'progress' => 25, 'status' => 'in_progress', 'budget_plan' => 750000], $b);

        $submitted = new Project(['name' => 'Proyek Demo (Diajukan)', 'description' => 'Terlihat oleh tester.']);
        $submitted->created_by = $pm->id;
        $submitted->status = ProjectStatus::Submitted;
        $submitted->save();

        $c = $make($submitted, 'Aktivitas C', ['weight' => 100]);
        $make($submitted, 'Sub C1', ['weight' => 100, 'progress' => 100, 'status' => 'completed'], $c);
    }
}