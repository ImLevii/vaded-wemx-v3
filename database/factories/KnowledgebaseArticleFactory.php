<?php

namespace Database\Factories;

use App\Models\KnowledgebaseArticle;
use App\Models\KnowledgebaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<KnowledgebaseArticle> */
class KnowledgebaseArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'knowledgebase_category_id' => KnowledgebaseCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => fake()->sentence(),
            'content' => "## Before you start\n\n".fake()->paragraph()."\n\n## Instructions\n\n".fake()->paragraph(),
            'is_published' => false,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }
}
