<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledgebase_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500);
            $table->string('icon')->default('server');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('knowledgebase_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledgebase_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500);
            $table->longText('content');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_published', 'knowledgebase_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledgebase_articles');
        Schema::dropIfExists('knowledgebase_categories');
    }
};
