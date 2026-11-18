<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWeightsRequest extends FormRequest
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
        return [
            'parent_task_id' => ['nullable', 'integer'],
            'weights' => ['required', 'array', 'min:1'],
            'weights.*.id' => ['required', 'integer', 'distinct'],
            'weights.*.weight' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}