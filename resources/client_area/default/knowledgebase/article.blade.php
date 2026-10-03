@extends('theme::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', $article->title.' | Knowledgebase')
@section('description', $article->summary)
@section('header')
<link rel="canonical" href="{{ route('knowledgebase.article', $article->slug) }}">
@endsection
@section('content')
<div class="vh-kb mx-auto max-w-6xl">
    <nav class="vh-kb-breadcrumb flex flex-wrap items-center gap-2" aria-label="Breadcrumb"><a href="{{ route('knowledgebase.index') }}">Knowledgebase</a><span aria-hidden="true">/</span><a href="{{ route('knowledgebase.category', $article->category->slug) }}">{{ $article->category->name }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $article->title }}</span></nav>
    <div class="vh-kb-reading-layout grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_260px]">
        <article class="vh-kb-reading min-w-0">
            <header class="vh-kb-article-heading">
                <span class="vh-kb-eyebrow">{{ $article->category->name }}</span>
                <h1>{{ $article->title }}</h1>
                <p>{{ $article->summary }}</p>
                <div class="vh-kb-meta flex flex-wrap items-center gap-4"><span>{{ $article->readingMinutes() }} min read</span><span>Updated <time datetime="{{ $article->updated_at->toDateString() }}">{{ $article->updated_at->format('M j, Y') }}</time></span></div>
            </header>
            <div class="vh-kb-prose">{!! $rendered['html'] !!}</div>
            <div class="vh-kb-article-end"><x-theme::icon name="check" /><p>Need more help? Include your service name and what you have tried when you contact support.</p></div>
        </article>
        <aside class="vh-kb-reading-sidebar flex flex-col gap-5">
            @if(count($rendered['headings']))
                <nav class="vh-kb-sidebar-panel" aria-label="On this page">
                    <h2>On this page</h2>
                    <div class="flex flex-col gap-3">@foreach($rendered['headings'] as $heading)<a href="#{{ $heading['id'] }}">{{ $heading['title'] }}</a>@endforeach</div>
                </nav>
            @endif
            <div class="vh-kb-sidebar-panel">
                <span class="vh-kb-category-icon"><x-theme::icon :name="$article->category->icon" /></span>
                <h2>{{ $article->category->name }}</h2>
                <p>{{ $article->category->description }}</p>
                <a href="{{ route('knowledgebase.category', $article->category->slug) }}" class="vh-kb-text-link">All category guides &rarr;</a>
            </div>
        </aside>
    </div>
    @if($relatedArticles->isNotEmpty())
        <section class="vh-kb-section" aria-labelledby="kb-related"><div class="vh-kb-section-heading"><span class="vh-kb-eyebrow">KEEP EXPLORING</span><h2 id="kb-related">Related guides</h2></div><div class="grid gap-3 md:grid-cols-2">@foreach($relatedArticles as $related)<x-theme::knowledgebase.article-card :article="$related" :show-category="false" />@endforeach</div></section>
    @endif
    <x-theme::knowledgebase.support />
</div>
@endsection
