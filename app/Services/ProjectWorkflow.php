<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ReviewDecision;
use App\Models\Project;
use App\Models\ProjectReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectWorkflow
{
    public function __construct(private WeightValidator $weights)
    {
    }

    public function readinessProblems(Project $project): array
    {
        $roots = $project->tasks()->whereNull('parent_task_id')->with('children')->get();

        if ($roots->isEmpty()) {
            return ['Proyek belum punya aktivitas.'];
        }

        $problems = [];

        if (! $this->weights->isValid($roots)) {
            $problems[] = 'Total bobot aktivitas utama harus 100% (saat ini '.$this->weights->total($roots).'%).';
        }

        foreach ($roots as $root) {
            if ($root->children->isEmpty()) {
                $problems[] = "Aktivitas \"{$root->name}\" belum punya subaktivitas.";

                continue;
            }

            if (! $this->weights->isValid($root->children)) {
                $problems[] = "Total bobot subaktivitas pada \"{$root->name}\" harus 100% (saat ini {$this->weights->total($root->children)}%).";
            }
        }

        return $problems;
    }

    public function activate(Project $project): Project
    {
        $this->requireStatus($project, [ProjectStatus::Planning], 'Hanya proyek berstatus Perencanaan yang bisa diaktifkan.');
        $this->requireReady($project);

        $project->status = ProjectStatus::Active;
        $project->activated_at = now();
        $project->save();

        return $project;
    }

    public function submit(Project $project): Project
    {
        $this->requireStatus(
            $project,
            [ProjectStatus::Active, ProjectStatus::Revision],
            'Hanya proyek berstatus Berjalan atau Revisi yang bisa diajukan.'
        );
        $this->requireReady($project);

        $project->status = ProjectStatus::Submitted;
        $project->save();

        return $project;
    }

    public function review(Project $project, User $reviewer, ReviewDecision $decision, ?string $notes): ProjectReview
    {
        $this->requireStatus($project, [ProjectStatus::Submitted], 'Hanya proyek berstatus Diajukan yang bisa diperiksa.');

        return DB::transaction(function () use ($project, $reviewer, $decision, $notes) {
            $review = new ProjectReview(['notes' => $notes]);
            $review->project_id = $project->id;
            $review->reviewer_id = $reviewer->id;
            $review->decision = $decision;
            $review->save();

            $project->status = $decision === ReviewDecision::Pass
                ? ProjectStatus::Done
                : ProjectStatus::Revision;
            $project->save();

            return $review;
        });
    }

    private function requireStatus(Project $project, array $allowed, string $message): void
    {
        if (! in_array($project->status, $allowed, true)) {
            abort(409, $message);
        }
    }

    private function requireReady(Project $project): void
    {
        $problems = $this->readinessProblems($project);

        if ($problems !== []) {
            throw ValidationException::withMessages(['project' => $problems]);
        }
    }
}