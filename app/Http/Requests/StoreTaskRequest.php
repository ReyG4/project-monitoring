<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        $project = $this->route('project');
        $targetEnd = ['nullable', 'date'];

        if ($this->filled('planned_start')) {
            $targetEnd[] = 'after_or_equal:planned_start';
        }

        return [
            'parent_task_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')
                    ->where('project_id', $project->id)
                    ->whereNull('parent_task_id')
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'weight' => ['sometimes', 'numeric', 'between:0,100'],
            'progress' => ['sometimes', 'numeric', 'between:0,100'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'planned_start' => ['nullable', 'date'],
            'target_end' => $targetEnd,
            'budget_plan' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}