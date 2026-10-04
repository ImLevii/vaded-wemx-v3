@extends('admin::layouts.wrapper', [
    'activePage' => 'settings',
])

@section('title', __('messages.settings'))

@props([
    'pages' => [
      'application' => [
        'name' => 'Application',
        'livewire' => admin_view_path('settings.livewire.application'),
      ],
      'branding' => [
        'name' => 'Branding',
        'livewire' => admin_view_path('settings.livewire.branding'),
      ],
      'appearance' => [
        'name' => 'Theme & Appearance',
        'livewire' => admin_view_path('settings.livewire.appearance'),
      ],
      'metrics' => [
        'name' => 'Metrics & Options',
        'livewire' => admin_view_path('settings.livewire.metrics'),
      ],
      'invoicing' => [
        'name' => 'Payments & Invoicing',
        'livewire' => admin_view_path('settings.livewire.invoicing'),
      ],
     'taxes' => [
        'name' => 'Taxes',
        'livewire' => admin_view_path('settings.livewire.taxes'),
      ],
      'authentication' => [
        'name' => 'Authentication',
        'livewire' => admin_view_path('settings.livewire.authentication'),
      ],
      'extension' => [
        'name' => 'Extension',
        'livewire' => admin_view_path('settings.livewire.extension-settings'),
      ],
    ],
])

@section('content')
    <div class="card">
        <div class="row g-0">
            <div class="col-12 col-md-3 border-end">
                <div class="card-body">
                    <h4 class="subheader">Application Settings</h4>
                    <div class="list-group list-group-transparent">
                        @foreach($pages as $key => $page)
                            @if($key == 'extension')
                                @continue
                            @endif
                            <a href="{{ route('admin.settings.index', ['page' => $key]) }}" class="list-group-item list-group-item-action d-flex align-items-center @if(request()->get('page', 'application') == $key) active @endif" wire:navigate>
                                {{ $page['name'] }}
                            </a>
                        @endforeach
                    </div>
                    <h4 class="subheader mt-4">Third Party</h4>
                    <div class="list-group list-group-transparent">
                        @foreach(\App\Models\Extension::allEnabled() as $extension)
                            @if(!$extension->extension()->hasSettingsPage())
                                @continue
                            @endif
                            <a href="{{ route('admin.settings.index', ['page' => 'extension', 'extension' => $extension->identifier]) }}" class="list-group-item list-group-item-action d-flex align-items-center @if(request()->get('page', 'application') == 'extension' AND request()->get('extension', 'application') == $extension->identifier) active @endif" wire:navigate>
                                {{ $extension->extension()->getSettingsTitle() }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-9 d-flex flex-column">
                @livewire($pages[request()->get('page', 'application')]['livewire'] ?? $pages['application']['livewire'])
            </div>
        </div>
    </div>
@endsection
