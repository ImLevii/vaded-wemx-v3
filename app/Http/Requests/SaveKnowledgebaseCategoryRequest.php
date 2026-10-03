<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKnowledgebaseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.knowledgebase.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('knowledgebase_categories')->ignore($this->route('category'))],
            'description' => ['required', 'string', 'max:500'],
            'icon' => ['required', Rule::in(['server', 'console', 'cube', 'cloud', 'bot', 'receipt', 'shield', 'users'])],
            'is_visible' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
