<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectReviewResource;
use App\Models\Project;
use App\Services\ProjectWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectWorkflowController extends Controller
{
    public function readiness(Project $project, ProjectWorkflow $workflow): JsonResponse
    {
        Gate::authorize('view', $project);

        $problems = $workflow->readinessProblems($project);

        return response()->json(['data' => ['ready' => $problems === [], 'problems' => $problems]]);
    }

    public function activate(Project $project, ProjectWorkflow $workflow): ProjectResource
    {
        Gate::authorize('activate', $project);

        return new ProjectResource($workflow->activate($project)->refresh());
    }

    public function submit(Project $project, ProjectWorkflow $workflow): ProjectResource
    {
        Gate::authorize('submit', $project);

        return new ProjectResource($workflow->submit($project)->refresh());
    }

    public function review(ReviewProjectRequest $request, Project $project, ProjectWorkflow $workflow): JsonResponse
    {
        $review = $workflow->review(
            $project,
            $request->user(),
            ReviewDecision::from($request->validated('decision')),
            $request->validated('notes')
        );

        return (new ProjectReviewResource($review->load('reviewer')))
            ->response()
            ->setStatusCode(201);
    }

    public function reviews(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return ProjectReviewResource::collection(
            $project->reviews()->with('reviewer')->latest()->get()
        );
    }
}