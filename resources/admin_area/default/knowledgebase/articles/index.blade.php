@extends('admin::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', 'Knowledgebase articles')
@section('actions')
<div class="col-auto"><a href="{{ route('admin.knowledgebase.articles.create') }}" class="btn btn-primary">Create article</a></div>
@endsection
@section('content')
@include('admin::knowledgebase.tabs')
<div class="card">
    <div class="card-header">
        <form method="GET" action="{{ route('admin.knowledgebase.articles.index') }}" class="d-flex gap-2 w-100">
            <label for="article-search" class="visually-hidden">Search articles</label>
            <input id="article-search" name="q" type="search" class="form-control" placeholder="Search titles and content, including drafts" value="{{ $search }}" maxlength="200">
            <button class="btn btn-outline-primary" type="submit">Search</button>
            @if($search !== '')<a href="{{ route('admin.knowledgebase.articles.index') }}" class="btn btn-outline-secondary">Clear</a>@endif
        </form>
    </div>
    <div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Article</th><th>Category</th><th>Visibility</th><th>Updated</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($articles as $article)
                <tr>
                    <td><a href="{{ route('admin.knowledgebase.articles.edit', $article) }}">{{ $article->title }}</a>@if($article->is_featured)<span class="badge bg-primary-lt ms-2">Featured</span>@endif</td>
                    <td>{{ $article->category->name }}</td>
                    <td><span @class(['badge', 'bg-success-lt' => $article->is_published && $article->category->is_visible, 'bg-secondary-lt' => !$article->is_published || !$article->category->is_visible])>{{ !$article->is_published ? 'Draft' : ($article->category->is_visible ? 'Published' : 'Category hidden') }}</span></td>
                    <td class="text-muted">{{ $article->updated_at->format('M j, Y') }}</td>
                    <td><div class="d-flex gap-2">
                        <a href="{{ route('admin.knowledgebase.articles.edit', $article) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <form method="POST" action="{{ route('admin.knowledgebase.articles.destroy', $article) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete {{ $article->title }}">Delete</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5">{{ $search !== '' ? 'No articles match your search.' : 'No articles yet. Create a category, then add your first guide.' }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($articles->hasPages())<div class="card-footer">{{ $articles->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
