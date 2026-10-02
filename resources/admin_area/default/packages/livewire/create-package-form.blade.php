<?php

use App\Models\Category;
use App\Models\Package;
use App\Models\ServerConnection;
use Livewire\Volt\Component;
use Illuminate\View\View;
use Illuminate\Support\Str;

new class extends Component
{
    public $categoryId;

    public $connectionId;

    public $name;

    public $slug;

    public $status = 'restricted';

    public function createPackage()
    {
        $package = Package::actions()->createPackageAsAdmin([
            'category_id' => $this->categoryId,
            'connection_id' => $this->connectionId,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
        ]);

        $this->redirect(route('admin.packages.edit', ['package' => $package->id]), true);
    }

    public function updated(): void
    {
        if(!$this->slug AND $this->name) {
            $this->slug = Str::slug($this->name);
        }
    }
}
?>

<form class="card" wire:submit="createPackage()">
    <div class="card-header">
        <h3 class="card-title">{{ __('messages.create_category') }}</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="category-input">{{ __('messages.category') }}</label>
            <div class="col">
                <select class="form-select @error('category_id') is-invalid @enderror" wire:model.change="categoryId" id="category-input">
                        <option value="">{{ __('messages.select_category') }}</option>
                    @foreach(Category::all() as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">{{ __('messages.select_package_category_desc') }}</small>
                @enderror
            </div>
        </div>
        @if($categoryId)
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="category-input">{{ __('messages.server_connection') }}</label>
                <div class="col">
                    <select class="form-select @error('connection_id') is-invalid @enderror" wire:model.change="connectionId" id="category-input">
                        <option value="">{{ __('messages.select_server') }}</option>
                        @foreach(ServerConnection::all() as $connection)
                            <option value="{{ $connection->id }}">{{ $connection->alias }} [{{ $connection->server->extension()->getName() }}]</option>
                        @endforeach
                    </select>
                    @error('connection_id')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">{{ __('messages.select_package_category_desc') }}</small>
                    @enderror
                </div>
            </div>
        @endif
        @if($categoryId AND $connectionId)
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
            <label class="col-3 col-form-label required" for="slug-input">{{ __('messages.slug') }}</label>
            <div class="col">
                <div class="input-group input-group-flat">
                      <span class="input-group-text">
                        {{ url('/packages') }}/
                      </span>
                    <input type="text" wire:model="slug" class="form-control ps-0"  id="slug-input" value="" autocomplete="off">
                </div>
                @error('slug')
                    <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">{{ __('messages.category_name_desc') }}</small>
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
                    <option value="inactive">{{ __('messages.category_option_disabled') }}</option>
                </select>
                @error('status')
                    <x-admin::form.error :message="$message" />
                @else
                    <small class="form-hint">{{ __('messages.category_option_desc') }}</small>
                @enderror
            </div>
        </div>
        @endif
    </div>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary">{{ __('messages.create') }}</button>
    </div>
</form>
