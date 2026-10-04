@extends('admin::layouts.wrapper', ['activePage' => 'images'])

@section('title', 'Image Library')

@section('actions')
    <div class="col-auto"><span class="vh-media-library-count"><x-admin::icon icon="photo" /><span data-library-total>{{ $images->count() }}</span> images</span></div>
@endsection

@section('content')
    <div class="vh-image-library" data-image-library data-max-size="{{ $maxFileSize }}" data-max-files="{{ config('images.max_files') }}">
        <div class="vh-media-intro">
            <div><span class="vh-appearance-eyebrow">Assets for your brand</span><h3>A home for every image.</h3><p>Upload, find and reuse your logos, icons and artwork across the site.</p></div>
            <span class="vh-media-picker-notice" data-picker-notice hidden><x-admin::icon icon="pointer" /> Choose an image to return it to your editor.</span>
        </div>

        <section class="card vh-media-upload" aria-labelledby="image-upload-heading">
            <div class="card-header"><h3 class="card-title" id="image-upload-heading"><x-admin::icon icon="cloud-upload" /> Upload images</h3><span class="vh-media-upload-limit">Up to {{ config('images.max_files') }} files · {{ $maxFileSizeLabel }} each</span></div>
            <form action="{{ route('admin.images.upload') }}" method="POST" enctype="multipart/form-data" data-upload-form>
                @csrf
                <div class="card-body">
                    <div class="vh-media-upload-layout">
                        <div>
                            <input type="file" name="images[]" id="image-files" accept="image/png,image/jpeg,image/gif,image/webp,.png,.jpg,.jpeg,.gif,.webp" multiple class="visually-hidden" data-file-input aria-describedby="image-upload-help">
                            <label for="image-files" class="vh-media-dropzone" data-dropzone tabindex="0" role="button">
                                <span class="vh-media-drop-icon"><x-admin::icon icon="cloud-upload" /></span>
                                <strong>Drag images here</strong><span>or browse files on your device</span>
                                <span class="vh-media-browse">Choose images <x-admin::icon icon="arrow-up-right" /></span>
                            </label>
                            <p class="form-hint mt-2 mb-0" id="image-upload-help">PNG, JPG, GIF and WebP. Existing images are never replaced.</p>
                        </div>
                        <div class="vh-media-upload-options">
                            <label for="image-file-name" class="form-label">Image name <span class="text-secondary">(optional)</span></label>
                            <x-admin::form.input id="image-file-name" name="file_name" :value="old('file_name')" placeholder="e.g. summer-banner" maxlength="180" data-file-name />
                            <p class="form-hint">For a single image. We add a unique suffix to keep every upload safe.</p>
                            <div class="vh-media-queue-summary" data-queue-summary>No files selected</div>
                            <button type="submit" class="btn btn-primary w-100" data-upload-button><x-admin::icon icon="upload" /><span>Upload images</span></button>
                            <button type="button" class="btn btn-link w-100 mt-2" data-clear-queue hidden>Clear selection</button>
                        </div>
                    </div>
                    @if($errors->any())
                        <div class="vh-media-feedback is-error mt-3" role="alert">@foreach($errors->all() as $message)<p class="mb-1">{{ $message }}</p>@endforeach</div>
                    @endif
                    <div class="vh-media-feedback mt-3" data-upload-feedback role="status" aria-live="polite" hidden></div>
                    <div class="vh-media-queue" data-upload-queue aria-label="Selected uploads"></div>
                    <div class="vh-media-progress" data-upload-progress hidden><div class="d-flex justify-content-between"><span data-progress-label>Uploading…</span><span data-progress-percent>0%</span></div><progress max="100" value="0" aria-label="Upload progress"></progress></div>
                </div>
            </form>
        </section>

        <section class="vh-media-collection" aria-labelledby="image-collection-heading">
            <div class="vh-media-toolbar">
                <div><h3 id="image-collection-heading">Your library</h3><p data-library-count aria-live="polite">{{ $images->count() }} images ready to use</p></div>
                <div class="vh-media-filters">
                    <div class="vh-media-search"><x-admin::icon icon="search" /><label for="image-search" class="visually-hidden">Search images</label><input type="search" id="image-search" class="form-control" placeholder="Search by filename…" data-image-search></div>
                    <label for="image-sort" class="visually-hidden">Sort images</label><select id="image-sort" class="form-select" data-image-sort><option value="newest">Newest first</option><option value="name">Name A–Z</option><option value="largest">Largest first</option></select>
                    <button type="button" class="btn btn-outline-secondary vh-media-preview-toggle" data-preview-toggle aria-pressed="false" aria-label="Use dark image preview backgrounds"><x-admin::icon icon="sun" /><span>Light previews</span></button>
                </div>
            </div>
            <div class="vh-media-gallery" data-image-gallery>
                @foreach($images as $image)
                    <article class="vh-media-card" data-image-card data-image-name="{{ $image['name'] }}" data-image-url="{{ $image['url'] }}" data-image-size="{{ $image['size'] }}" data-image-modified="{{ $image['modified'] }}">
                        <a class="vh-media-thumbnail" href="{{ $image['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="View {{ $image['name'] }} at full size"><img src="{{ $image['url'] }}" alt="{{ $image['name'] }}" loading="lazy" decoding="async"><span class="vh-media-format">{{ $image['format'] }}</span><span class="vh-media-image-fallback" hidden><x-admin::icon icon="photo-off" />Preview unavailable</span><span class="vh-media-selected" hidden><x-admin::icon icon="check" /></span></a>
                        <div class="vh-media-card-body">
                            <h4 title="{{ $image['name'] }}">{{ $image['name'] }}</h4><p>{{ $image['size_label'] }}</p>
                            <label class="visually-hidden" for="image-url-{{ $loop->index }}">URL for {{ $image['name'] }}</label><input id="image-url-{{ $loop->index }}" class="form-control vh-media-url" value="{{ $image['url'] }}" readonly data-image-url-input>
                            <div class="vh-media-card-actions"><button type="button" class="btn btn-primary" data-select-image aria-pressed="false"><x-admin::icon icon="pointer" /><span>Select</span></button><button type="button" class="btn btn-outline-secondary" data-copy-image><x-admin::icon icon="copy" /><span>Copy URL</span></button></div>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="vh-media-empty" data-library-empty @if($images->isNotEmpty()) hidden @endif><span><x-admin::icon icon="photo-plus" /></span><h4 data-empty-title>Your library starts here</h4><p data-empty-description>Upload your first image to make it available across the site.</p><button type="button" class="btn btn-outline-secondary" data-empty-action>Choose images</button></div>
        </section>
        <div class="vh-media-toast" data-image-toast role="status" aria-live="polite" hidden></div>
    </div>
@endsection

@section('scripts')
    <script src="{{ admin_asset('js/image-library.js') }}&updated={{ filemtime(resource_path('admin_area/default/assets/js/image-library.js')) }}" data-navigate-once defer></script>
@endsection
