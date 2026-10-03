@extends('admin::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', $category->exists ? 'Edit knowledgebase category' : 'Create knowledgebase category')
@section('content')
@include('admin::knowledgebase.tabs')
<form method="POST" action="{{ $category->exists ? route('admin.knowledgebase.categories.update', $category) : route('admin.knowledgebase.categories.store') }}">
    @csrf
    @if($category->exists)@method('PUT')@endif
    <div class="card"><div class="card-body">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="mb-3"><label for="category-name" class="form-label required">Name</label><input id="category-name" name="name" class="form-control" value="{{ old('name', $category->name) }}" maxlength="255" required></div>
                <div class="mb-3"><label for="category-slug" class="form-label required">URL slug</label><input id="category-slug" name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" placeholder="minecraft" maxlength="255" pattern="[a-z0-9]+(-[a-z0-9]+)*" required><div class="form-hint">Lowercase letters, numbers, and hyphens. Changing this changes the category's public URL.</div></div>
                <div><label for="category-description" class="form-label required">Description</label><textarea id="category-description" name="description" class="form-control" rows="3" maxlength="500" required>{{ old('description', $category->description) }}</textarea></div>
            </div>
            <div class="col-lg-4">
                <div class="mb-3"><label for="category-icon" class="form-label required">Icon</label><select id="category-icon" name="icon" class="form-select" required>@foreach(['server' => 'Server', 'console' => 'Console', 'cube' => 'Minecraft', 'cloud' => 'Cloud', 'bot' => 'Bot', 'receipt' => 'Billing', 'shield' => 'Security', 'users' => 'Community'] as $icon => $label)<option value="{{ $icon }}" @selected(old('icon', $category->icon) === $icon)>{{ $label }}</option>@endforeach</select></div>
                <div class="mb-4"><label for="category-order" class="form-label required">Sort order</label><input id="category-order" name="sort_order" type="number" class="form-control" value="{{ old('sort_order', $category->sort_order) }}" min="0" max="100000" required></div>
                <input type="hidden" name="is_visible" value="0"><label class="form-check"><input type="checkbox" name="is_visible" value="1" class="form-check-input" @checked(old('is_visible', $category->is_visible))><span class="form-check-label">Show category publicly</span><span class="form-check-description">Hiding a category hides all its articles from browsing, search, and direct links.</span></label>
            </div>
        </div>
    </div><div class="card-footer d-flex gap-2"><button type="submit" class="btn btn-primary">{{ $category->exists ? 'Save category' : 'Create category' }}</button><a href="{{ route('admin.knowledgebase.categories.index') }}" class="btn btn-outline-secondary">Back to categories</a></div></div>
</form>
@endsection
