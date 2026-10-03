@extends('theme::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', 'Knowledgebase')
@section('description', 'Vaded Hosting guides for Minecraft, game servers, VPS hosting, Discord bots, billing, and account security.')
@section('content')
<div class="vh-kb mx-auto max-w-6xl">
    <section class="vh-kb-hero">
        <div class="vh-kb-hero-grid" aria-hidden="true"></div>
        <div class="vh-kb-hero-top flex flex-wrap items-center justify-between gap-4">
            <span class="vh-kicker"><span></span> VADED / HELP CENTER</span>
            <span class="vh-kb-meta">{{ $categories->sum('articles_count') }} guides &middot; {{ $categories->count() }} categories</span>
        </div>
        <div class="vh-kb-hero-copy">
            <span class="vh-kb-eyebrow">A LITTLE GUIDANCE. A LOT OF POSSIBILITIES.</span>
            <h1>Good servers.<br><em>Great answers.</em></h1>
            <p>From your first connection to your next big community.<br class="hidden sm:block"> Find the help you need to keep things running.</p>
        </div>
        <x-theme::knowledgebase.search :action="route('knowledgebase.index')" :search="$search" :categories="$categories" :selected-category="$selectedCategory" />
        <div class="vh-kb-quick-links flex flex-wrap items-center gap-x-5 gap-y-3">
            <span>Start here</span>
            @foreach($categories->take(3) as $category)<a href="{{ route('knowledgebase.category', $category->slug) }}">{{ $category->name }}</a>@endforeach
        </div>
    </section>

    @if($search !== '' || $selectedCategory)
        <section class="vh-kb-section" aria-labelledby="kb-results">
            <div class="vh-kb-section-heading flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="vh-kb-eyebrow">FIND YOUR ANSWER</span>
                    <h2 id="kb-results">{{ $search !== '' ? 'Search results' : $selectedCategory->name }}</h2>
                    <p>{{ $articles->total() }} {{ Str::plural('guide', $articles->total()) }}{{ $search !== '' ? ' for “'.$search.'”' : '' }}{{ $selectedCategory && $search !== '' ? ' in '.$selectedCategory->name : '' }}</p>
                </div>
                <a href="{{ route('knowledgebase.index') }}" class="vh-kb-text-link">Clear filters &times;</a>
            </div>
            <div class="vh-kb-article-list grid gap-3 md:grid-cols-2">
                @forelse($articles as $article)
                    <x-theme::knowledgebase.article-card :article="$article" />
                @empty
                    <div class="vh-kb-empty md:col-span-2">
                        <x-theme::icon name="search" />
                        <h3>No guides found</h3>
                        <p>Try a shorter search, a different term, or another category.</p>
                        <a href="{{ route('knowledgebase.index') }}" class="vh-kb-text-link">Browse all categories &rarr;</a>
                    </div>
                @endforelse
            </div>
            <div class="vh-kb-pagination mt-6">{{ $articles->links() }}</div>
        </section>
    @else
        <section class="vh-kb-section" aria-labelledby="kb-categories">
            <div class="vh-kb-section-heading flex flex-wrap items-end justify-between gap-4">
                <div><span class="vh-kb-eyebrow">YOUR NEXT STEP STARTS HERE</span><h2 id="kb-categories">Browse by category</h2></div>
                <p>Choose your service. Find your answer.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($categories as $category)
                    <a href="{{ route('knowledgebase.category', $category->slug) }}" class="vh-kb-category-card flex flex-col gap-5">
                        <div class="flex items-center justify-between gap-4"><span class="vh-kb-category-icon"><x-theme::icon :name="$category->icon" /></span><span class="vh-kb-meta">{{ $category->articles_count }} {{ Str::plural('guide', $category->articles_count) }}</span></div>
                        <div class="flex flex-col gap-2"><h3>{{ $category->name }}</h3><p>{{ $category->description }}</p></div>
                        <span class="vh-kb-category-link flex items-center justify-between">Explore guides <span aria-hidden="true">&rarr;</span></span>
                    </a>
                @empty
                    <div class="vh-kb-empty lg:col-span-3"><h3>Guides are on the way</h3><p>Check back soon for help with your hosting services.</p></div>
                @endforelse
            </div>
        </section>
        @if($featuredArticles->isNotEmpty())
            <section class="vh-kb-section" aria-labelledby="kb-featured">
                <div class="vh-kb-section-heading"><span class="vh-kb-eyebrow">A GOOD PLACE TO BEGIN</span><h2 id="kb-featured">Recommended guides</h2><p>The essentials for getting your service up and running.</p></div>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach($featuredArticles as $article)<x-theme::knowledgebase.article-card :article="$article" />@endforeach
                </div>
            </section>
        @endif
    @endif
    <x-theme::knowledgebase.support />
</div>
@endsection
