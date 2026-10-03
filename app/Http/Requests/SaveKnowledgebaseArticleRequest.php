<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveKnowledgebaseArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.knowledgebase.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'knowledgebase_category_id' => ['required', 'integer', 'exists:knowledgebase_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('knowledgebase_articles')->ignore($this->route('article'))],
            'summary' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:100000'],
            'is_published' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
