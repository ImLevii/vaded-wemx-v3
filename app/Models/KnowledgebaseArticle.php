<?php

namespace App\Models;

use Database\Factories\KnowledgebaseArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgebaseArticle extends Model
{
    /** @use HasFactory<KnowledgebaseArticleFactory> */
    use HasFactory;

    protected $fillable = ['knowledgebase_category_id', 'title', 'slug', 'summary', 'content', 'is_published', 'is_featured', 'sort_order'];

    protected $attributes = ['is_published' => false, 'is_featured' => false, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_featured' => 'boolean', 'sort_order' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgebaseCategory::class, 'knowledgebase_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereHas('category', fn (Builder $category): Builder => $category->visible());
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(function (Builder $match) use ($term): void {
                $match->whereLike('title', '%'.$term.'%')
                    ->orWhereLike('summary', '%'.$term.'%')
                    ->orWhereLike('content', '%'.$term.'%');
            });
        }

        return $query;
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->content)) / 200));
    }
}
