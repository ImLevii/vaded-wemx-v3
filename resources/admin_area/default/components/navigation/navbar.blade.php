<div class="navbar-nav flex-row ms-auto p-1">
    <div class="d-flex align-items-center">
        {{-- Language Switcher --}}
        <div class="dropdown d-none d-sm-block">
            <button class="nav-link px-0 me-2 d-flex align-items-center" id="languageSwitcher" data-bs-toggle="dropdown"
                    aria-expanded="false" data-bs-placement="bottom">
                <span class="flag flag-xxs flag-country-{{ auth()->user()->language()->flag }} me-1"></span>
                {{ auth()->user()->language()->native ?? auth()->user()->language()->name }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageSwitcher">
                @foreach(config('languages.languages') as $langKey => $language)
                    <li>
                        <a class="dropdown-item @if(auth()?->user()->language === $langKey) active @endif text-decoration-none text-body"
                           wire:navigate href="{{ route('admin.toggle.language', $langKey) }}">
                            <span class="ms-2 d-flex align-items-center ">
                                <span class="flag flag-xxs flag-country-{{ $language['flag'] }} me-1"></span>

                                {{ $language['native'] ?? $language['name'] }} ({{ strtoupper($langKey) }})
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <a href="#" class="nav-link px-0 me-2"
           data-bs-placement="bottom" data-bs-toggle="modal" data-bs-target="#modal-search"
           aria-label="Search" data-bs-original-title="Search">
            <x-admin::icon icon="search" class="icon"/>
        </a>

        @if(auth()->user()?->hasPermission('admin.dashboard'))
        <div class="dropdown vh-theme-controls">
            <button type="button" class="nav-link me-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Theme controls"><x-admin::icon icon="palette" /></button>
            <div class="dropdown-menu dropdown-menu-end">
                <label for="quick-theme-mode" class="form-label">Your display</label>
                <select id="quick-theme-mode" class="form-select" data-personal-theme><option value="site">Site default</option><option value="auto">Day / night</option><option value="system">Device preference</option><option value="light">Light</option><option value="dark">Dark</option></select>
                @perm('admin.settings.index')<a href="{{ route('admin.settings.index', ['page' => 'appearance']) }}" wire:navigate class="dropdown-item mt-2">Site appearance &amp; seasonal logo <x-admin::icon icon="arrow-right" /></a>@endperm
            </div>
        </div>
        <a href="?theme=dark" data-theme-mode="dark" class="nav-link px-0 hide-theme-dark me-2" data-bs-toggle="tooltip"
           data-bs-placement="bottom"
           aria-label="{{ __('messages.enable_dark') }}" data-bs-original-title="{{ __('messages.enable_dark') }}">
            <x-admin::icon icon="moon" class="icon"/>
        </a>
        <a href="?theme=light" data-theme-mode="light" class="nav-link px-0 hide-theme-light me-2" data-bs-toggle="tooltip"
           data-bs-placement="bottom"
           aria-label="{{ __('messages.enable_light') }}" data-bs-original-title="{{ __('messages.enable_light') }}">
            <x-admin::icon icon="sun" class="icon"/>
        </a>
        @endif
    </div>

    <livewire:admin.user-menu/>
</div>
