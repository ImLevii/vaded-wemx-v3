<?php

use App\Models\Package;
use Livewire\Volt\Component;
use Illuminate\View\View;
use App\Models\Category;

new class extends Component
{
    public $package;

    public $name;

    public $icon;

    public $slug;

    public $status;

    public $short_description;

    public $categoryId;

    public $description;

    public $global_quantity;

    public $client_quantity;

    public function mount(Package $package): void
    {
        $this->package = $package;
        $this->name = $package->name;
        $this->icon = $package->icon;
        $this->slug = $package->slug;
        $this->status = $package->status;
        $this->short_description = $package->short_description;
        $this->categoryId = $package->category_id;
        $this->description = $package->description;
        $this->global_quantity = $package->global_quantity;
        $this->client_quantity = $package->client_quantity;
    }

    public function updatePackage(): void
    {
        $this->resetErrorBag();

        Package::actions()->updatePackageAsAdmin([
            'package_id' => $this->package->id,
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'icon' => $this->icon,
            'slug' => $this->slug,
            'status' => $this->status,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'global_quantity' => $this->global_quantity,
            'client_quantity' => $this->client_quantity,
        ]);
    }

    public function deletePackage()
    {
        $this->resetErrorBag();

        Package::actions()->deletePackageAsAdmin([
            'package_id' => $this->package->id,
        ]);

        return redirect()
            ->route('admin.packages.index')
            ->with('success', 'Package deleted successfully');
    }
}
?>
<form wire:submit="updatePackage()">
    <div class="mb-3">
        <x-admin::form.label for="name" label="Name"/>
        <x-admin::form.input type="text" wire:model="name" id="name" name="name" placeholder="Name"/>
        @error('name')
            <x-admin::form.error :message="$message" />
        @else
            <x-admin::form.description description="The name of the package"/>
        @enderror
    </div>
    <div class="mb-3">
        <label class="col-3 col-form-label" for="icon-input">{{ __('messages.icon') }}</label>
        <div class="">
            <div>
                @if($icon)
                    <img src="{{ $icon }}" class="avatar avatar-xl mb-3" alt="package icon">
                @endif

                <div>
                    <input type="text" wire:model.change="icon" class="form-control mb-3" id="icon-input">
                    @error('icon')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint mb-3">
                            The direct URL of the icon for the package. Recommended size 100x100px.
                        </small>
                    @enderror
                    <x-admin::button href="{{ route('admin.images.index') }}" target="_blank" class="mb-2">
                        <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-file-upload"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M12 11v6" /><path d="M9.5 13.5l2.5 -2.5l2.5 2.5" /></svg>
                        Upload Images
                    </x-admin::button>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <x-admin::form.label for="slug" label="Slug"/>
        <div class="input-group input-group-flat">
              <span class="input-group-text">
                https://wemx.net/packages/
              </span>
            <input type="text" wire:model="slug" class="form-control ps-0"  id="slug-input" value="" autocomplete="off">
        </div>
        @error('slug')
            <x-admin::form.error :message="$message" />
        @else
            <x-admin::form.description description="The slug of the package"/>
        @enderror
    </div>
    <div class="mb-3">
        <x-admin::form.label for="category" label="Category"/>
        <x-admin::form.select wire:model="categoryId" id="category" name="category" :options="Category::pluck('name', 'id')"/>
        @error('category_id')
            <x-admin::form.error :message="$message" />
        @else
            <x-admin::form.description description="The name of the package"/>
        @enderror
    </div>
    <div class="mb-3">
        <x-admin::form.label for="status" label="Status"/>
        <x-admin::form.select
            wire:model="status"
            id="status"
            name="status"
            :options="[
                'restricted' => __('messages.category_option_restricted'),
                'unlisted' => __('messages.category_option_unlisted'),
                'active' => __('messages.category_option_active'),
                'inactive' => __('messages.category_option_disabled'),
            ]"
        />
        @error('status')
            <x-admin::form.error :message="$message" />
        @else
            <x-admin::form.description :description="__('messages.category_option_desc')"/>
        @enderror
    </div>
    <div class="mb-3">
        <x-admin::form.label for="server" label="Server"/>
        <x-admin::form.input type="text" id="server" name="server" value="{{ $this->package->serverConnection->alias }}" disabled/>
        <x-admin::form.description description="The server connection of the package"/>
    </div>
    <div class="mb-3">
        <x-admin::form.label for="short_description" label="Short Description"/>
        <x-admin::form.textarea id="short_description" wire:model="short_description" content="" rows="2"/>
        @error('short_description')
            <x-admin::form.error :message="$message" />
        @else
            <x-admin::form.description description="The short description of the package"/>
        @enderror
    </div>
    <div class="mb-3">
        <x-admin::form.label for="description" label="Description"/>
        <x-admin::form.markdown-editor :content="$description" :rows="10" wire:model="description" />
        @error('description')
            <x-admin::form.error :message="$message" />
        @else
            <x-admin::form.description description="The description of the package. Markdown is supported."/>
        @enderror
    </div>
    <div class="row mb-3">
        <div class="mb-3 col-12 col-md-6 col-lg-6">
            <x-admin::form.label for="global_quantity" label="Global Quantity"/>
            <x-admin::form.input type="number" wire:model="global_quantity" id="global_quantity" name="global_quantity" placeholder="Global Quantity" min="-1" value="-1"/>
            @error('global_quantity')
                <x-admin::form.error :message="$message" />
            @else
                <x-admin::form.description description="The quantity of this package available globally"/>
            @enderror
        </div>
        <div class="mb-3 col-12 col-md-6 col-lg-6">
            <x-admin::form.label for="client_quantity" label="Quantity per Customer"/>
            <x-admin::form.input type="number" wire:model="client_quantity" id="client_quantity" name="client_quantity" placeholder="Quantity per Customer" min="-1" value="-1"/>
            @error('client_quantity')
                <x-admin::form.error :message="$message" />
            @else
                <x-admin::form.description description="The quantity a single client is allowed to order of this package"/>
                @enderror
        </div>
    </div>
    <div class="mb-3 d-flex justify-content-end gap-2">
        <x-admin::button
            type="button"
            color="danger"
            label="Delete Package"
            wire:click="deletePackage"
            wire:confirm.prompt="Are you sure you want to delete this package? This will terminate and delete all related orders. Enter the Package ID to confirm|{{ $this->package->id }}"
        />
        <x-admin::button type="submit" label="Save Changes"/>
    </div>
    @error('package_id')
        <x-admin::form.error :message="$message" />
    @enderror
</form>
