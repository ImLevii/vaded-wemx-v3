<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveKnowledgebaseCategoryRequest;
use App\Models\KnowledgebaseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgebaseCategoryController extends Controller
{
    public function index(): View
    {
        $categories = KnowledgebaseCategory::query()->withCount('articles')->orderBy('sort_order')->orderBy('name')->paginate(20);

        return view('admin::knowledgebase.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin::knowledgebase.categories.form', ['category' => new KnowledgebaseCategory]);
    }

    public function store(SaveKnowledgebaseCategoryRequest $request): RedirectResponse
    {
        $category = KnowledgebaseCategory::query()->create($request->validated());

        return redirect()->route('admin.knowledgebase.categories.edit', $category)->with('success', 'Category created.');
    }

    public function edit(KnowledgebaseCategory $category): View
    {
        return view('admin::knowledgebase.categories.form', compact('category'));
    }

    public function update(SaveKnowledgebaseCategoryRequest $request, KnowledgebaseCategory $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', 'Category saved.');
    }

    public function destroy(KnowledgebaseCategory $category): RedirectResponse
    {
        if ($category->articles()->exists()) {
            return back()->with('error', 'Move or delete the articles in this category before deleting it.');
        }

        $category->delete();

        return redirect()->route('admin.knowledgebase.categories.index')->with('success', 'Category deleted.');
    }
}
