@extends('theme::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', $category->name.' | Knowledgebase')
@section('description', $category->description)
@section('content')
<div class="vh-kb mx-auto max-w-6xl">
    <nav class="vh-kb-breadcrumb flex flex-wrap items-center gap-2" aria-label="Breadcrumb"><a href="{{ route('knowledgebase.index') }}">Knowledgebase</a><span aria-hidden="true">/</span><span aria-current="page">{{ $category->name }}</span></nav>
    <section class="vh-kb-category-hero">
        <div class="flex items-start gap-5"><span class="vh-kb-category-icon"><x-theme::icon :name="$category->icon" /></span><div><span class="vh-kb-eyebrow">VADED / KNOWLEDGEBASE</span><h1>{{ $category->name }}</h1><p>{{ $category->description }}</p></div></div>
        <x-theme::knowledgebase.search :action="route('knowledgebase.category', $category->slug)" :search="$search" :placeholder="'Search '.$category->name.' guides'" />
    </section>
    <section class="vh-kb-section" aria-labelledby="kb-category-guides">
        <div class="vh-kb-section-heading flex flex-wrap items-end justify-between gap-4">
            <div><h2 id="kb-category-guides">{{ $search !== '' ? 'Search results' : 'All guides' }}</h2><p>{{ $articles->total() }} {{ Str::plural('guide', $articles->total()) }}{{ $search !== '' ? ' for “'.$search.'”' : '' }}</p></div>
            @if($search !== '')<a href="{{ route('knowledgebase.category', $category->slug) }}" class="vh-kb-text-link">Clear search &times;</a>@endif
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            @forelse($articles as $article)
                <x-theme::knowledgebase.article-card :article="$article" :show-category="false" />
            @empty
                <div class="vh-kb-empty md:col-span-2"><x-theme::icon name="search" /><h3>{{ $search !== '' ? 'No matching guides' : 'No guides published yet' }}</h3><p>{{ $search !== '' ? 'Try a different search or browse another category.' : 'Check back soon for guides in this category.' }}</p><a href="{{ route('knowledgebase.index') }}" class="vh-kb-text-link">Browse all categories &rarr;</a></div>
            @endforelse
        </div>
        <div class="vh-kb-pagination mt-6">{{ $articles->links() }}</div>
    </section>
    <x-theme::knowledgebase.support />
</div>
@endsection
