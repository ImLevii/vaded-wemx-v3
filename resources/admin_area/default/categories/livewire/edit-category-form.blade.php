<?php

use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Illuminate\View\View;

new class extends Component
{
    public $category_id;

    public $iconUrl;

    public $name;

    public $description = '';

    public $slug;

    public $status = 'restricted';

    public $uploadFromUrl = false;

    public function mount($category)
    {
        $this->category_id = $category->id;
        $this->iconUrl = $category->icon;
        $this->name = $category->name;
        $this->description = $category->description;
        $this->slug = $category->slug;
        $this->status = $category->status;
    }

    #[Computed]
    public function category()
    {
        return Category::find($this->category_id);
    }

    public function updateCategory()
    {
        $category = Category::actions()->updateCategoryAsAdmin([
            'category_id' => $this->category_id,
            'icon' => $this->iconUrl,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'status' => $this->status,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully');
    }

    public function deleteCategory()
    {
        $this->resetErrorBag();

        Category::actions()->deleteCategoryAsAdmin([
            'category_id' => $this->category_id,
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully');
    }

    public function updated(): void
    {
        if($this->name AND !$this->slug) {
            $this->slug = Str::slug($this->name);
        }
    }
}
?>

<form class="card" wire:submit="updateCategory()">
    <div class="card-header">
        <h3 class="card-title">{{ __('messages.edit_category') }}</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="icon-input">{{ __('messages.icon') }}</label>
            <div class="col">
                <div>
                    @if($iconUrl)
                        <img src="{{ $iconUrl }}" class="avatar avatar-xl mb-3" alt="category icon">
                    @endif

                    <div>
                        <input type="text" wire:model.change="iconUrl" class="form-control mb-1" aria-describedby="icon_url-input" id="icon_url-input" placeholder="{{ __('messages.icon_url') }}">
                        @error('icon')
                        <x-admin::form.error :message="$message" />
                        @else
                            <small class="form-hint mb-3">
                                The direct URL of the icon for the category. Recommended size 100x100px.
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
        <div class="mb-3 row">
            <label class="col-3 col-form-label required" for="name-input">{{ __('messages.name') }}</label>
            <div class="col">
                <input type="text" wire:model.change="name" class="form-control @error('name') is-invalid @enderror" aria-describedby="name-input" id="name-input" placeholder="Name">
                @error('name')
                <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">{{ __('messages.category_name_desc') }}</small>
                    @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="description-input">{{ __('messages.description') }}</label>
            <div class="col">
                <textarea class="form-control @error('description') is-invalid @enderror" wire:model="description" id="description-input" rows="2" placeholder="Content.."></textarea>
                @error('description')
                <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">{{ __('messages.category_description_desc') }}</small>
                    @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label required" for="slug-input">{{ __('messages.slug') }}</label>
            <div class="col">
                <div class="input-group input-group-flat">
                              <span class="input-group-text">
                                https://wemx.net/category/
                              </span>
                    <input type="text" wire:model="slug" class="form-control ps-0"  id="slug-input" value="" autocomplete="off">
                </div>
                @error('slug')
                <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">{{ __('messages.category_slug_desc') }}</small>
                    @enderror
            </div>
        </div>
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="status-input">{{ __('messages.status') }}</label>
            <div class="col">
                <select class="form-select @error('status') is-invalid @enderror" wire:model="status" id="status-input">
                    <option value="restricted">{{ __('messages.category_option_restricted') }}</option>
                    <option value="unlisted">{{ __('messages.category_option_unlisted') }}</option>
                    <option value="active">{{ __('messages.category_option_active') }}</option>
                    <option value="disabled">{{ __('messages.category_option_disabled') }}</option>
                </select>
                @error('status')
                <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">{{ __('messages.category_option_desc') }}</small>
                    @enderror
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <button
            type="button"
            class="btn btn-danger me-2"
            wire:click="deleteCategory"
            wire:confirm.prompt="Are you sure you want to delete this category? This will delete all packages in this category and terminate related orders. Enter the Category ID to confirm|{{ $category_id }}"
        >
            Delete Category
        </button>
        <button type="submit" class="btn btn-primary">{{ __('messages.update') }}</button>
        @error('category_id')
            <x-admin::form.error :message="$message" />
        @enderror
        @error('package_id')
            <x-admin::form.error :message="$message" />
        @enderror
    </div>
</form>
