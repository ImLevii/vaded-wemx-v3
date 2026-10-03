@extends('admin::layouts.wrapper', ['activePage' => 'knowledgebase'])
@section('title', 'Preview saved article')
@section('content')
@include('admin::knowledgebase.tabs')
<div class="alert alert-info">This is the saved version. {{ $article->is_published && $article->category->is_visible ? 'This article is public.' : 'This article is hidden from the public knowledgebase.' }}</div>
<div class="card"><div class="card-body">
    <p class="text-muted">{{ $article->category->name }}</p><h1>{{ $article->title }}</h1><p class="text-muted">{{ $article->summary }}</p>
    <div class="markdown">{!! $rendered['html'] !!}</div>
    <a href="{{ route('admin.knowledgebase.articles.edit', $article) }}" class="btn btn-primary mt-4">Continue editing</a>
</div></div>
@endsection
