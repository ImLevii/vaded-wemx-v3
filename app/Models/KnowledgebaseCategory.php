<?php

namespace App\Models;

use Database\Factories\KnowledgebaseCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgebaseCategory extends Model
{
    /** @use HasFactory<KnowledgebaseCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'icon', 'sort_order', 'is_visible'];

    protected $attributes = ['icon' => 'server', 'sort_order' => 0, 'is_visible' => true];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'sort_order' => 'integer'];
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgebaseArticle::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }
}
