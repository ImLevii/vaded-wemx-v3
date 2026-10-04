<?php

use Livewire\Volt\Component;
use Illuminate\View\View;
use App\Models\Setting;

new class extends Component
{
    public $app_logo;

    public $favicon;

    public function mount()
    {
        $this->app_logo = settings('app_logo', '/assets/common/img/vaded-app-logo.png');
        $this->favicon = settings('favicon', '/assets/common/img/vaded-app-logo.png');
    }

    public function saveChanges()
    {
        $this->resetErrorBag();

        settings([
            'app_logo' => $this->app_logo,
            'favicon' => $this->favicon,
        ]);

        $this->dispatch('alert', 'success', 'Settings saved successfully.');
    }
}

?>

<div>
    <x-admin::settings.page-form title="Branding">
            <div class="mb-4">
                <h3 class="card-title">Application Logo</h3>
                <p class="card-subtitle">
                    The application logo displayed to users.
                </p>
                <div class="row g-2">
                    <div class="mb-1">
                        <span class="avatar avatar-xl" style="background-image: url({{ $app_logo }})"></span>
                    </div>
                    <div class="col-auto">
                        <x-admin::form.input wire:model.change="app_logo" name="app_logo" placeholder="Application Logo" />
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('admin.images.index') }}" target="_blank" rel="opener" onclick="window.imagePickerTarget = document.querySelector('[name=app_logo]')" class="btn btn-1">
                            Upload
                        </a>
                    </div>
                    @error('app_name')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">
                            Last modified 2 days ago
                        </small>
                    @enderror
                </div>
            </div>

            <div class="mb-4">
                <h3 class="card-title">Favicon</h3>
                <p class="card-subtitle">
                    The application favicon displayed in the browser.
                </p>
                <div class="row g-2">
                    <div class="mb-1">
                        <span class="avatar avatar-xl" style="background-image: url({{ $favicon }})"></span>
                    </div>
                    <div class="col-auto">
                        <x-admin::form.input wire:model.change="favicon" name="favicon" placeholder="Favicon" />
                    </div>
                    <div class="col-auto">
                        <a href="{{ route('admin.images.index') }}" target="_blank" rel="opener" onclick="window.imagePickerTarget = document.querySelector('[name=favicon]')" class="btn btn-1">
                            Upload
                        </a>
                    </div>
                    @error('app_name')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">
                            Last modified 2 days ago
                        </small>
                    @enderror
                </div>
            </div>
    </x-admin::settings.page-form>
</div>
