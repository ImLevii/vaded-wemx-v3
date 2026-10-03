<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgebaseSearchRequest;
use App\Models\KnowledgebaseArticle;
use App\Models\KnowledgebaseCategory;
use App\Support\KnowledgebaseMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class KnowledgebaseController extends Controller
{
    public function index(KnowledgebaseSearchRequest $request): View
    {
        $search = trim($request->validated('q') ?? '');
        $categorySlug = $request->validated('category');
        $categories = KnowledgebaseCategory::query()->visible()
            ->withCount(['articles' => fn (Builder $articles): Builder => $articles->published()])
            ->orderBy('sort_order')->orderBy('name')->get();
        $selectedCategory = $categorySlug ? $categories->firstWhere('slug', $categorySlug) : null;
        abort_if($categorySlug && ! $selectedCategory, 404);

        $articles = null;
        $featuredArticles = collect();

        if ($search !== '' || $selectedCategory) {
            $articles = KnowledgebaseArticle::query()->published()->with('category')
                ->search($search)
                ->when($selectedCategory, fn (Builder $query): Builder => $query->whereBelongsTo($selectedCategory, 'category'))
                ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('title')
                ->paginate(12)->appends($request->safe()->only(['q', 'category']));
        } else {
            $featuredArticles = KnowledgebaseArticle::query()->published()->with('category')
                ->where('is_featured', true)->orderBy('sort_order')->orderBy('title')->limit(6)->get();
        }

        return view('theme::knowledgebase.index', compact('categories', 'articles', 'featuredArticles', 'search', 'selectedCategory'));
    }

    public function category(KnowledgebaseSearchRequest $request, KnowledgebaseCategory $category): View
    {
        abort_unless($category->is_visible, 404);
        $search = trim($request->validated('q') ?? '');
        $articles = $category->articles()->published()->with('category')->search($search)
            ->orderBy('sort_order')->orderBy('title')->paginate(12)->appends(['q' => $search]);

        return view('theme::knowledgebase.category', compact('category', 'articles', 'search'));
    }

    public function show(KnowledgebaseArticle $article, KnowledgebaseMarkdown $markdown): View
    {
        $article->load('category');
        abort_unless($article->is_published && $article->category->is_visible, 404);
        $rendered = $markdown->render($article->content);
        $relatedArticles = $article->category->articles()->published()->whereKeyNot($article->id)
            ->orderBy('sort_order')->orderBy('title')->limit(4)->get();

        return view('theme::knowledgebase.article', compact('article', 'rendered', 'relatedArticles'));
    }
}
