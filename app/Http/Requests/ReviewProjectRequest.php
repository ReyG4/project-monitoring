<?php

namespace App\Http\Requests;

use App\Enums\ReviewDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('review', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'notes' => ['required_if:decision,keluhan', 'nullable', 'string', 'max:5000'],
        ];
    }
}