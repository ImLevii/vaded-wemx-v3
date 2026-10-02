<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClientNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
    }

    public function test_guest_navigation_only_lists_active_categories_in_sort_order(): void
    {
        foreach (['active', 'restricted', 'unlisted', 'disabled'] as $status) {
            Category::query()->create(['icon' => '', 'name' => 'Navigation '.$status, 'slug' => 'navigation-'.$status, 'status' => $status, 'sort_order' => 5]);
        }
        Category::query()->create(['icon' => '', 'name' => 'First service', 'slug' => 'first-service', 'status' => 'active', 'sort_order' => 1]);

        $html = $this->headerHtml('/');

        $this->assertStringContainsString('Navigation active', $html);
        $this->assertStringContainsString(route('categories.index', ['category' => 'first-service']).'#pricing', $html);
        $this->assertLessThan(strpos($html, 'Navigation active'), strpos($html, 'First service'));
        foreach (['restricted', 'unlisted', 'disabled'] as $status) {
            $this->assertStringNotContainsString('Navigation '.$status, $html);
        }
    }

    public function test_admin_navigation_includes_restricted_but_not_unlisted_or_disabled_categories(): void
    {
        $admin = User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']);
        foreach (['active', 'restricted', 'unlisted', 'disabled'] as $status) {
            Category::query()->create(['icon' => '', 'name' => 'Navigation '.$status, 'slug' => 'navigation-'.$status, 'status' => $status]);
        }

        $this->actingAs($admin);
        $html = $this->headerHtml('/dashboard');

        $this->assertStringContainsString('Navigation active', $html);
        $this->assertStringContainsString('Navigation restricted', $html);
        $this->assertStringNotContainsString('Navigation unlisted', $html);
        $this->assertStringNotContainsString('Navigation disabled', $html);
    }

    public function test_customer_dashboard_has_the_service_menu_without_restricted_categories(): void
    {
        $customer = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        Category::query()->create(['icon' => '', 'name' => 'Public navigation service', 'slug' => 'public-service', 'status' => 'active']);
        Category::query()->create(['icon' => '', 'name' => 'Private navigation service', 'slug' => 'private-service', 'status' => 'restricted']);

        $this->actingAs($customer);
        $html = $this->headerHtml('/dashboard');

        $this->assertStringContainsString('Public navigation service', $html);
        $this->assertStringNotContainsString('Private navigation service', $html);
        $this->assertStringContainsString('aria-controls="hosting-menu"', $html);
    }

    public function test_empty_navigation_keeps_a_link_to_the_store(): void
    {
        $html = $this->headerHtml('/');

        $this->assertStringContainsString('New services are on the way.', $html);
        $this->assertStringContainsString(route('categories.index').'#services', $html);
        $this->assertStringNotContainsString('id="games-menu-trigger"', $html);
        $this->assertStringNotContainsString('id="cloud-menu-trigger"', $html);
    }

    public function test_navigation_groups_services_and_preserves_uncategorized_services(): void
    {
        foreach ([
            ['name' => 'Minecraft navigation', 'slug' => 'minecraft-server-hosting'],
            ['name' => 'Cloud navigation', 'slug' => 'vps'],
            ['name' => 'Bot navigation', 'slug' => 'discord-bot-hosting'],
            ['name' => 'Other navigation', 'slug' => 'new-service'],
        ] as $category) {
            Category::query()->create([...$category, 'icon' => '', 'status' => 'active']);
        }

        $html = $this->headerHtml('/');
        $games = str($html)->after('id="games-menu"')->before('</li>')->toString();
        $cloud = str($html)->after('id="cloud-menu"')->before('</li>')->toString();
        $bots = str($html)->after('id="bots-menu"')->before('</li>')->toString();
        $other = str($html)->after('id="hosting-menu"')->before('</li>')->toString();

        $this->assertStringContainsString('Minecraft navigation', $games);
        $this->assertStringNotContainsString('Cloud navigation', $games);
        $this->assertStringContainsString('Cloud navigation', $cloud);
        $this->assertStringNotContainsString('Minecraft navigation', $cloud);
        $this->assertStringContainsString('Bot navigation', $bots);
        $this->assertStringContainsString('Other navigation', $other);
    }

    public function test_hidden_categories_do_not_create_empty_navigation_groups(): void
    {
        Category::query()->create(['name' => 'Hidden game', 'slug' => 'minecraft-server-hosting', 'icon' => '', 'status' => 'unlisted']);
        Category::query()->create(['name' => 'Public cloud', 'slug' => 'vps', 'icon' => '', 'status' => 'active']);

        $html = $this->headerHtml('/');

        $this->assertStringNotContainsString('id="games-menu-trigger"', $html);
        $this->assertStringNotContainsString('Hidden game', $html);
        $this->assertStringContainsString('id="cloud-menu-trigger"', $html);
        $this->assertStringNotContainsString('id="hosting-menu-trigger"', $html);
    }

    public function test_category_text_is_escaped_and_missing_images_use_the_placeholder(): void
    {
        Category::query()->create(['icon' => '',
            'name' => '<script>unsafe title</script>',
            'slug' => 'escaped-service',
            'status' => 'active',
            'description' => '<b>Plain description</b>',
        ]);

        $html = $this->headerHtml('/');

        $this->assertStringContainsString('&lt;script&gt;unsafe title&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Plain description&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>unsafe title</script>', $html);
        $this->assertStringContainsString('/assets/common/img/category-placeholder.png', $html);
    }

    private function headerHtml(string $path): string
    {
        return str($this->get($path)->assertOk()->getContent())->before('</header>')->toString();
    }
}
