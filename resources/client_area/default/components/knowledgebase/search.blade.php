@props(['action', 'search' => '', 'categories' => null, 'selectedCategory' => null, 'placeholder' => 'Search guides, questions, or error messages'])
<form method="GET" action="{{ $action }}" class="vh-kb-search flex flex-col gap-3 sm:flex-row" role="search" aria-label="Search the knowledgebase">
    <div class="vh-kb-search-input flex min-w-0 flex-1 items-center gap-3">
        <x-theme::icon name="search" />
        <label class="sr-only" for="kb-query">Search the knowledgebase</label>
        <input id="kb-query" type="search" name="q" value="{{ $search }}" placeholder="{{ $placeholder }}" maxlength="200" autocomplete="off" class="min-w-0 flex-1">
    </div>
    @if($categories)
        <label class="sr-only" for="kb-category">Filter by category</label>
        <select id="kb-category" name="category" aria-label="Filter by category">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->slug }}" @selected($selectedCategory?->id === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    @endif
    <button type="submit" class="vh-kb-button">Search <span aria-hidden="true">&rarr;</span></button>
</form>
@error('q')<p role="alert" class="vh-kb-error mt-3">{{ $message }}</p>@enderror
@error('category')<p role="alert" class="vh-kb-error mt-3">{{ $message }}</p>@enderror
