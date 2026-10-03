@extends('admin::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', 'Knowledgebase categories')
@section('actions')
<div class="col-auto"><a href="{{ route('admin.knowledgebase.categories.create') }}" class="btn btn-primary">Create category</a></div>
@endsection
@section('content')
@include('admin::knowledgebase.tabs')
<div class="card">
    <div class="table-responsive"><table class="table table-vcenter card-table">
        <thead><tr><th>Category</th><th>Articles</th><th>Sort order</th><th>Visibility</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($categories as $category)
                <tr>
                    <td><a href="{{ route('admin.knowledgebase.categories.edit', $category) }}">{{ $category->name }}</a><div class="text-muted small">{{ $category->description }}</div></td>
                    <td>{{ $category->articles_count }}</td><td>{{ $category->sort_order }}</td>
                    <td><span @class(['badge', 'bg-success-lt' => $category->is_visible, 'bg-secondary-lt' => !$category->is_visible])>{{ $category->is_visible ? 'Visible' : 'Hidden' }}</span></td>
                    <td><div class="d-flex gap-2">
                        <a href="{{ route('admin.knowledgebase.categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <form method="POST" action="{{ route('admin.knowledgebase.categories.destroy', $category) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger" @disabled($category->articles_count > 0) aria-label="Delete {{ $category->name }}" title="{{ $category->articles_count ? 'Move or delete articles first' : 'Delete empty category' }}">Delete</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5">No categories yet. Add a category to organize your guides.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($categories->hasPages())<div class="card-footer">{{ $categories->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
