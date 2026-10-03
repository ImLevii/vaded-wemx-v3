@extends('admin::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', $article->exists ? 'Edit knowledgebase article' : 'Create knowledgebase article')
@section('content')
@include('admin::knowledgebase.tabs')
@if($categories->isEmpty())
    <div class="alert alert-info">Create a category before adding an article. <a href="{{ route('admin.knowledgebase.categories.create') }}">Create category</a></div>
@else
<form method="POST" action="{{ $article->exists ? route('admin.knowledgebase.articles.update', $article) : route('admin.knowledgebase.articles.store') }}">
    @csrf
    @if($article->exists)@method('PUT')@endif
    <div class="row g-4">
        <div class="col-lg-8"><div class="card"><div class="card-body">
            <div class="mb-3"><label for="article-title" class="form-label required">Title</label><input id="article-title" name="title" class="form-control" value="{{ old('title', $article->title) }}" maxlength="255" required></div>
            <div class="mb-3"><label for="article-slug" class="form-label required">URL slug</label><input id="article-slug" name="slug" class="form-control" value="{{ old('slug', $article->slug) }}" placeholder="connect-to-your-minecraft-server" maxlength="255" pattern="[a-z0-9]+(-[a-z0-9]+)*" required><div class="form-hint">Lowercase letters, numbers, and hyphens. Changing this changes the article's public URL.</div></div>
            <div class="mb-3"><label for="article-summary" class="form-label required">Summary</label><textarea id="article-summary" name="summary" class="form-control" rows="3" maxlength="500" required>{{ old('summary', $article->summary) }}</textarea><div class="form-hint">Shown in search results and used as the page description.</div></div>
            <div><label for="article-content" class="form-label required">Guide content</label><textarea id="article-content" name="content" class="form-control font-monospace" rows="22" maxlength="100000" required>{{ old('content', $article->content) }}</textarea><div class="form-hint">Use Markdown: ## Section heading, **bold**, numbered lists, links, and fenced code blocks. Headings create the article's table of contents. Raw HTML is stripped.</div></div>
        </div></div></div>
        <div class="col-lg-4"><div class="card"><div class="card-body">
            <div class="mb-3"><label for="article-category" class="form-label required">Category</label><select id="article-category" name="knowledgebase_category_id" class="form-select" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) old('knowledgebase_category_id', $article->knowledgebase_category_id) === $category->id)>{{ $category->name }}{{ $category->is_visible ? '' : ' (hidden)' }}</option>@endforeach</select></div>
            <div class="mb-4"><label for="article-order" class="form-label required">Sort order</label><input id="article-order" type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $article->sort_order) }}" min="0" max="100000" required><div class="form-hint">Lower numbers appear first.</div></div>
            <input type="hidden" name="is_published" value="0"><label class="form-check mb-3"><input type="checkbox" name="is_published" value="1" class="form-check-input" @checked(old('is_published', $article->is_published))><span class="form-check-label">Publish article</span><span class="form-check-description">Unpublished drafts are only available in the admin area. Hidden categories also hide their articles.</span></label>
            <input type="hidden" name="is_featured" value="0"><label class="form-check mb-4"><input type="checkbox" name="is_featured" value="1" class="form-check-input" @checked(old('is_featured', $article->is_featured))><span class="form-check-label">Feature on the knowledgebase home page</span></label>
            <div class="d-flex flex-column gap-2">
                <button type="submit" class="btn btn-primary">{{ $article->exists ? 'Save article' : 'Create article' }}</button>
                @if($article->exists)
                    <a href="{{ route('admin.knowledgebase.articles.preview', $article) }}" class="btn btn-outline-secondary">Preview saved article</a>
                    @if($article->is_published && $article->category->is_visible)<a href="{{ route('knowledgebase.article', $article->slug) }}" class="btn btn-outline-secondary">View public article</a>@endif
                @endif
                <a href="{{ route('admin.knowledgebase.articles.index') }}" class="btn btn-outline-secondary">Back to articles</a>
            </div>
        </div></div></div>
    </div>
</form>
@endif
@endsection
