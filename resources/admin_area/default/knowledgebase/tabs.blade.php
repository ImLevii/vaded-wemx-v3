<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('admin.knowledgebase.articles.index') }}" @class(['btn', 'btn-primary' => request()->routeIs('admin.knowledgebase.articles.*'), 'btn-outline-secondary' => !request()->routeIs('admin.knowledgebase.articles.*')])>Articles</a>
    <a href="{{ route('admin.knowledgebase.categories.index') }}" @class(['btn', 'btn-primary' => request()->routeIs('admin.knowledgebase.categories.*'), 'btn-outline-secondary' => !request()->routeIs('admin.knowledgebase.categories.*')])>Categories</a>
    <a href="{{ route('knowledgebase.index') }}" class="btn btn-outline-secondary">View public knowledgebase <span class="ms-2" aria-hidden="true">&rarr;</span></a>
</div>
@if($errors->any())
    <div class="alert alert-danger" role="alert"><div><h3 class="alert-title">Please check the following fields</h3><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif
