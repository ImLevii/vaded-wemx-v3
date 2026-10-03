<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KnowledgebaseSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:255', 'exists:knowledgebase_categories,slug'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
