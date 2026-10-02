<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostingCatalog
{
    /** @var Collection<int, Category>|null */
    private ?Collection $categories = null;

    /** @return Collection<int, Category> */
    public function categories(): Collection
    {
        return $this->categories ??= Category::query()
            ->when(auth()->user()?->isAdmin(),
                fn (Builder $query): Builder => $query->whereNotIn('status', ['disabled', 'unlisted']),
                fn (Builder $query): Builder => $query->where('status', 'active'))
            ->with(['packages' => fn (HasMany $query): HasMany => $query
                ->visibleToUser(auth()->user(), includeUnlisted: false)->with('prices')])
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    public function selectedCategory(): ?Category
    {
        $slug = request()->query('category');

        if ($slug === null) {
            return $this->categories()->first();
        }

        if (! is_string($slug)) {
            return null;
        }

        $category = Category::query()->where('slug', $slug)->first();

        return $category && ($category->status === 'active' || $category->status === 'unlisted'
            || ($category->status === 'restricted' && auth()->user()?->isAdmin())) ? $category : null;
    }
}
