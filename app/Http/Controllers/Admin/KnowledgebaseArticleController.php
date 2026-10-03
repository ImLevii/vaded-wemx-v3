<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgebaseSearchRequest;
use App\Http\Requests\SaveKnowledgebaseArticleRequest;
use App\Models\KnowledgebaseArticle;
use App\Models\KnowledgebaseCategory;
use App\Support\KnowledgebaseMarkdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgebaseArticleController extends Controller
{
    public function index(KnowledgebaseSearchRequest $request): View
    {
        $search = trim($request->validated('q') ?? '');
        $articles = KnowledgebaseArticle::query()->with('category')->search($search)
            ->latest('updated_at')->paginate(20)->appends(['q' => $search]);

        return view('admin::knowledgebase.articles.index', compact('articles', 'search'));
    }

    public function create(): View
    {
        $article = new KnowledgebaseArticle;
        $categories = KnowledgebaseCategory::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin::knowledgebase.articles.form', compact('article', 'categories'));
    }

    public function store(SaveKnowledgebaseArticleRequest $request): RedirectResponse
    {
        $article = KnowledgebaseArticle::query()->create($request->validated());

        return redirect()->route('admin.knowledgebase.articles.edit', $article)->with('success', 'Article created.');
    }

    public function edit(KnowledgebaseArticle $article): View
    {
        $article->load('category');
        $categories = KnowledgebaseCategory::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin::knowledgebase.articles.form', compact('article', 'categories'));
    }

    public function update(SaveKnowledgebaseArticleRequest $request, KnowledgebaseArticle $article): RedirectResponse
    {
        $article->update($request->validated());

        return back()->with('success', 'Article saved.');
    }

    public function preview(KnowledgebaseArticle $article, KnowledgebaseMarkdown $markdown): View
    {
        $article->load('category');
        $rendered = $markdown->render($article->content);

        return view('admin::knowledgebase.articles.preview', compact('article', 'rendered'));
    }

    public function destroy(KnowledgebaseArticle $article): RedirectResponse
    {
        $article->delete();

        return redirect()->route('admin.knowledgebase.articles.index')->with('success', 'Article deleted.');
    }
}
