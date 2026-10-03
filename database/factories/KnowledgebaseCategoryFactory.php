<?php

namespace Database\Factories;

use App\Models\KnowledgebaseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<KnowledgebaseCategory> */
class KnowledgebaseCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return ['name' => $name, 'slug' => Str::slug($name), 'description' => fake()->sentence(), 'icon' => 'server', 'sort_order' => 0, 'is_visible' => true];
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_visible' => false]);
    }
}
