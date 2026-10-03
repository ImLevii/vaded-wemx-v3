<?php

namespace Tests\Feature;

use App\Models\KnowledgebaseArticle;
use App\Models\KnowledgebaseCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\KnowledgebaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class KnowledgebaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
    }

    public function test_homepage_is_public_and_shows_starter_categories_and_guides(): void
    {
        $this->get(route('knowledgebase.index'))->assertOk()
            ->assertSeeText('Good servers.')->assertSeeText('Great answers.')
            ->assertSeeText('24 guides')->assertSeeText('6 categories')
            ->assertSeeText('Minecraft')->assertSeeText('Discord bots')
            ->assertSeeText('Recommended guides')
            ->assertSee(route('knowledgebase.article', 'connect-to-your-minecraft-server'), false);

        $this->assertSame(24, KnowledgebaseArticle::query()->count());
        $this->assertSame(6, KnowledgebaseCategory::query()->count());
    }

    public function test_site_and_client_navigation_link_to_the_knowledgebase(): void
    {
        $this->get('/')->assertOk()->assertSee(route('knowledgebase.index'), false);
        $customer = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $this->actingAs($customer)->get(route('dashboard'))->assertOk()->assertSee(route('knowledgebase.index'), false);
    }

    public function test_categories_only_show_published_articles_and_unknown_slugs_return_404(): void
    {
        $category = KnowledgebaseCategory::factory()->create();
        $article = KnowledgebaseArticle::factory()->published()->for($category, 'category')->create(['title' => 'Public category guide']);
        KnowledgebaseArticle::factory()->for($category, 'category')->create(['title' => 'Private category draft']);

        $this->get(route('knowledgebase.category', $category->slug))->assertOk()
            ->assertSeeText($article->title)->assertDontSeeText('Private category draft');
        $this->get('/knowledgebase/category/not-a-real-category')->assertNotFound();
        $this->get('/knowledgebase/article/not-a-real-article')->assertNotFound();
    }

    public function test_drafts_and_hidden_categories_are_excluded_from_all_public_queries(): void
    {
        $hidden = KnowledgebaseCategory::factory()->hidden()->create(['name' => 'Internal documentation']);
        $hiddenArticle = KnowledgebaseArticle::factory()->published()->for($hidden, 'category')->create(['title' => 'Internal secret needle']);
        $draft = KnowledgebaseArticle::factory()->create(['title' => 'Private draft needle', 'content' => 'needle', 'is_featured' => true]);

        $this->get(route('knowledgebase.index'))->assertOk()->assertDontSeeText('Internal documentation')->assertDontSeeText($draft->title);
        $this->get('/knowledgebase?q=needle')->assertOk()->assertSeeText('No guides found')
            ->assertDontSeeText($hiddenArticle->title)->assertDontSeeText($draft->title);
        $this->get(route('knowledgebase.article', $draft->slug))->assertNotFound();
        $this->get(route('knowledgebase.article', $hiddenArticle->slug))->assertNotFound();
        $this->get(route('knowledgebase.category', $hidden->slug))->assertNotFound();
        $this->get(route('knowledgebase.index', ['category' => $hidden->slug]))->assertNotFound();
    }

    public function test_search_matches_title_summary_and_body_case_insensitively(): void
    {
        $category = KnowledgebaseCategory::factory()->create();
        foreach (['title', 'summary', 'content'] as $field) {
            KnowledgebaseArticle::factory()->published()->for($category, 'category')
                ->create([$field => 'Aurora troubleshooting', 'slug' => 'aurora-'.$field]);
        }

        $this->get('/knowledgebase?q=AURORA')->assertOk()->assertSeeText('3 guides')
            ->assertSee(route('knowledgebase.article', 'aurora-title'), false)
            ->assertSee(route('knowledgebase.article', 'aurora-summary'), false)
            ->assertSee(route('knowledgebase.article', 'aurora-content'), false);
    }

    public function test_search_combines_terms_and_filters_by_category(): void
    {
        $category = KnowledgebaseCategory::factory()->create();
        $match = KnowledgebaseArticle::factory()->published()->for($category, 'category')
            ->create(['title' => 'Copper connection', 'summary' => 'Diagnose a handshake failure.']);
        $wrongCategory = KnowledgebaseArticle::factory()->published()
            ->create(['title' => 'Copper handshake in another category']);
        $partial = KnowledgebaseArticle::factory()->published()->for($category, 'category')
            ->create(['title' => 'Copper only']);

        $this->get(route('knowledgebase.index', ['q' => 'copper handshake', 'category' => $category->slug]))
            ->assertOk()->assertSeeText($match->title)
            ->assertDontSeeText($wrongCategory->title)->assertDontSeeText($partial->title);
        $this->get(route('knowledgebase.category', ['category' => $category->slug, 'q' => 'handshake']))
            ->assertOk()->assertSeeText($match->title)->assertDontSeeText($partial->title);
    }

    public function test_invalid_search_inputs_are_rejected_without_exceptions(): void
    {
        foreach ([
            ['q' => ['minecraft']],
            ['q' => str_repeat('x', 201)],
            ['category' => ['minecraft']],
            ['category' => 'nonexistent'],
            ['page' => -1],
            ['page' => 'invalid'],
        ] as $query) {
            $this->getJson(route('knowledgebase.index', $query))->assertUnprocessable();
        }
    }

    public function test_search_pagination_preserves_filters(): void
    {
        $category = KnowledgebaseCategory::factory()->create();
        KnowledgebaseArticle::factory()->published()->for($category, 'category')->count(13)->create(['summary' => 'Paginationmarker guide']);

        $response = $this->get(route('knowledgebase.index', ['q' => 'Paginationmarker', 'category' => $category->slug]));
        $response->assertOk()->assertSeeText('13 guides');
        $articles = $response->viewData('articles');
        $this->assertCount(12, $articles);
        $this->assertStringContainsString('q=Paginationmarker', $articles->nextPageUrl());
        $this->assertStringContainsString('category='.$category->slug, $articles->nextPageUrl());
        $this->get($articles->nextPageUrl())->assertOk();
    }

    public function test_articles_render_safe_markdown_and_valid_unique_heading_links(): void
    {
        $article = KnowledgebaseArticle::factory()->published()->create([
            'title' => 'Safe rendering example',
            'content' => "## First **step**\n\nA useful guide.\n\n## First **step**\n\n[Unsafe](javascript:alert%281%29)\n\n<script>alert('xss')</script>\n\n![Unsafe image](javascript:alert%281%29)\n\n\x60\x60\x60text\n## Not a heading\n\x60\x60\x60",
        ]);
        $response = $this->get(route('knowledgebase.article', $article->slug));
        $response->assertOk()->assertSeeText('On this page')
            ->assertSee('<strong>step</strong>', false)
            ->assertDontSee("alert('xss')", false)->assertDontSee('href="javascript:', false)
            ->assertDontSee('src="javascript:', false);
        $rendered = $response->viewData('rendered');
        $this->assertCount(2, $rendered['headings']);
        $this->assertSame('First step', $rendered['headings'][0]['title']);
        $this->assertNotSame($rendered['headings'][0]['id'], $rendered['headings'][1]['id']);
        foreach ($rendered['headings'] as $heading) {
            $response->assertSee('id="'.$heading['id'].'"', false)->assertSee('href="#'.$heading['id'].'"', false);
        }
    }

    public function test_related_guides_exclude_drafts_and_other_categories(): void
    {
        $category = KnowledgebaseCategory::factory()->create();
        $article = KnowledgebaseArticle::factory()->published()->for($category, 'category')->create();
        $related = KnowledgebaseArticle::factory()->published()->for($category, 'category')->create();
        $draft = KnowledgebaseArticle::factory()->for($category, 'category')->create();
        $other = KnowledgebaseArticle::factory()->published()->create();

        $this->get(route('knowledgebase.article', $article->slug))->assertOk()
            ->assertSeeText($related->title)->assertDontSeeText($draft->title)->assertDontSeeText($other->title);
    }

    public function test_search_text_and_article_metadata_are_escaped(): void
    {
        $search = '<script>alert(123)</script>';
        $this->get(route('knowledgebase.index', ['q' => $search]))->assertOk()
            ->assertSee('&lt;script&gt;alert(123)&lt;/script&gt;', false)->assertDontSee($search, false);
        $article = KnowledgebaseArticle::factory()->published()->create(['title' => $search, 'summary' => '<img src=x onerror=alert(1)>']);
        $this->get(route('knowledgebase.article', $article->slug))->assertOk()
            ->assertDontSee($search, false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_admin_can_create_preview_update_unpublish_and_delete_an_article(): void
    {
        $this->signInAdmin();
        $category = KnowledgebaseCategory::factory()->create();
        $input = $this->articleInput($category);
        $this->get(route('admin.knowledgebase.articles.create'))->assertOk();
        $this->post(route('admin.knowledgebase.articles.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
        $article = KnowledgebaseArticle::query()->where('slug', $input['slug'])->firstOrFail();
        $this->assertFalse($article->is_published);
        $this->get(route('knowledgebase.article', $article->slug))->assertNotFound();
        $this->get(route('admin.knowledgebase.articles.edit', $article))->assertOk()->assertSee('value="'.$input['title'].'"', false);
        $this->get(route('admin.knowledgebase.articles.preview', $article))->assertOk()->assertSeeText('This article is hidden');

        $this->put(route('admin.knowledgebase.articles.update', $article), [...$input, 'is_published' => 1, 'is_featured' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('knowledgebase.article', $article->slug))->assertOk()->assertSeeText($input['title']);
        $this->put(route('admin.knowledgebase.articles.update', $article), $input)->assertSessionHasNoErrors();
        $this->get(route('knowledgebase.article', $article->slug))->assertNotFound();
        $this->delete(route('admin.knowledgebase.articles.destroy', $article))->assertRedirect(route('admin.knowledgebase.articles.index'));
        $this->assertModelMissing($article);
    }

    public function test_admin_article_validation_rejects_duplicates_invalid_categories_and_bad_fields(): void
    {
        $this->signInAdmin();
        $category = KnowledgebaseCategory::factory()->create();
        $article = KnowledgebaseArticle::factory()->create(['slug' => 'existing-guide']);
        $input = $this->articleInput($category);
        $this->post(route('admin.knowledgebase.articles.store'), [
            ...$input, 'slug' => $article->slug, 'knowledgebase_category_id' => 999999, 'title' => '', 'content' => '', 'is_published' => 'yes', 'sort_order' => -1,
        ])->assertSessionHasErrors(['slug', 'knowledgebase_category_id', 'title', 'content', 'is_published', 'sort_order']);
        $this->post(route('admin.knowledgebase.articles.store'), [...$input, 'slug' => 'Unsafe/Path'])->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('knowledgebase_articles', ['slug' => 'my-test-guide']);
    }

    public function test_admin_can_create_hide_update_and_delete_an_empty_category(): void
    {
        $this->signInAdmin();
        $input = ['name' => 'New category', 'slug' => 'new-category', 'description' => 'Useful guides', 'icon' => 'cloud', 'sort_order' => 9, 'is_visible' => 1];
        $this->get(route('admin.knowledgebase.categories.index'))->assertOk();
        $this->get(route('admin.knowledgebase.categories.create'))->assertOk();
        $this->post(route('admin.knowledgebase.categories.store'), $input)->assertSessionHasNoErrors()->assertRedirect();
        $category = KnowledgebaseCategory::query()->where('slug', 'new-category')->firstOrFail();
        $this->get(route('admin.knowledgebase.categories.edit', $category))->assertOk();
        $this->put(route('admin.knowledgebase.categories.update', $category), [...$input, 'is_visible' => 0])->assertSessionHasNoErrors();
        $this->get(route('knowledgebase.category', $category->slug))->assertNotFound();
        $this->delete(route('admin.knowledgebase.categories.destroy', $category))->assertRedirect();
        $this->assertModelMissing($category);
    }

    public function test_categories_with_articles_cannot_be_deleted_and_category_fields_are_validated(): void
    {
        $this->signInAdmin();
        $category = KnowledgebaseCategory::factory()->create();
        KnowledgebaseArticle::factory()->for($category, 'category')->create();
        $this->delete(route('admin.knowledgebase.categories.destroy', $category))->assertSessionHas('error');
        $this->assertModelExists($category);
        $this->post(route('admin.knowledgebase.categories.store'), [
            'name' => '', 'slug' => $category->slug, 'description' => '', 'icon' => 'arbitrary', 'sort_order' => -1, 'is_visible' => 'yes',
        ])->assertSessionHasErrors(['name', 'slug', 'description', 'icon', 'sort_order', 'is_visible']);
    }

    public function test_customer_and_unpermitted_staff_cannot_read_or_mutate_admin_content(): void
    {
        $user = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $article = KnowledgebaseArticle::factory()->create();
        $category = $article->category;
        $this->actingAs($user)->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
        $this->assertAdminRoutesForbidden($article, $category);
        $role = Role::query()->create(['name' => 'Support without knowledgebase permission', 'super_admin' => false]);
        $user->roles()->create(['role_id' => $role->id]);
        $user->unsetRelation('roles');
        $this->assertAdminRoutesForbidden($article, $category);
        $this->assertModelExists($article);
        $this->assertModelExists($category);
    }

    public function test_staff_with_the_knowledgebase_permission_can_manage_content(): void
    {
        $staff = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $role = Role::query()->create(['name' => 'Knowledgebase editors', 'super_admin' => false]);
        $role->permissions()->create(['permission' => 'admin.knowledgebase.manage']);
        $staff->roles()->create(['role_id' => $role->id]);
        $this->actingAs($staff)->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.knowledgebase.articles.index'))->assertOk()->assertSeeText('Knowledgebase');
        $this->post(route('admin.knowledgebase.articles.store'), $this->articleInput(KnowledgebaseCategory::factory()->create()))
            ->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_reseeding_preserves_editor_changes_and_does_not_duplicate_content(): void
    {
        $article = KnowledgebaseArticle::query()->firstOrFail();
        $article->update(['title' => 'Edited by Vaded staff', 'is_published' => false]);
        $category = $article->category;
        $category->update(['name' => 'Custom category name', 'is_visible' => false]);
        $this->seed(KnowledgebaseSeeder::class);
        $this->assertSame(24, KnowledgebaseArticle::query()->count());
        $this->assertSame(6, KnowledgebaseCategory::query()->count());
        $this->assertSame('Edited by Vaded staff', $article->fresh()->title);
        $this->assertFalse($article->fresh()->is_published);
        $this->assertSame('Custom category name', $category->fresh()->name);
        $this->assertFalse($category->fresh()->is_visible);
    }

    private function signInAdmin(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']))
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
    }

    /** @return array{knowledgebase_category_id: int, title: string, slug: string, summary: string, content: string, is_published: int, is_featured: int, sort_order: int} */
    private function articleInput(KnowledgebaseCategory $category): array
    {
        return ['knowledgebase_category_id' => $category->id, 'title' => 'My test guide', 'slug' => 'my-test-guide', 'summary' => 'A guide summary', 'content' => "## Instructions\n\nFollow these steps.", 'is_published' => 0, 'is_featured' => 0, 'sort_order' => 0];
    }

    private function assertAdminRoutesForbidden(KnowledgebaseArticle $article, KnowledgebaseCategory $category): void
    {
        foreach (['articles.index', 'articles.create', 'categories.index', 'categories.create'] as $name) {
            $this->get(route('admin.knowledgebase.'.$name))->assertForbidden();
        }
        $this->get(route('admin.knowledgebase.articles.edit', $article))->assertForbidden();
        $this->get(route('admin.knowledgebase.articles.preview', $article))->assertForbidden();
        $this->get(route('admin.knowledgebase.categories.edit', $category))->assertForbidden();
        $this->post(route('admin.knowledgebase.articles.store'), $this->articleInput($category))->assertForbidden();
        $this->put(route('admin.knowledgebase.articles.update', $article), $this->articleInput($category))->assertForbidden();
        $this->delete(route('admin.knowledgebase.articles.destroy', $article))->assertForbidden();
        $this->post(route('admin.knowledgebase.categories.store'), [])->assertForbidden();
        $this->put(route('admin.knowledgebase.categories.update', $category), [])->assertForbidden();
        $this->delete(route('admin.knowledgebase.categories.destroy', $category))->assertForbidden();
    }
}
