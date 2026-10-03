@props(['article', 'showCategory' => true])
<a href="{{ route('knowledgebase.article', $article->slug) }}" class="vh-kb-article-card flex items-start gap-4">
    <span class="vh-kb-document" aria-hidden="true"><x-theme::icon name="guide" /></span>
    <div class="flex min-w-0 flex-1 flex-col gap-2">
        @if($showCategory)<span class="vh-kb-eyebrow">{{ $article->category->name }}</span>@endif
        <h3>{{ $article->title }}</h3>
        <p>{{ $article->summary }}</p>
        <span class="vh-kb-meta">{{ $article->readingMinutes() }} min read</span>
    </div>
    <span class="vh-kb-row-arrow" aria-hidden="true">&rarr;</span>
</a>
