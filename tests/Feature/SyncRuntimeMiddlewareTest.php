<?php

namespace Tests\Feature;

use App\Http\Middleware\SyncRuntimeMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SyncRuntimeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('env', 'production');
        config([
            'app.license_key' => '',
            'app.license_bypass' => false,
            'cache.default' => 'array',
        ]);

        Http::preventStrayRequests();
    }

    #[DataProvider('licenseRoutes')]
    public function test_admin_without_a_key_can_reach_license_routes(string $routeName): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $response = (new SyncRuntimeMiddleware)->handle(
            $this->requestForRoute($routeName),
            fn (Request $request) => response('License page')
        );

        $this->assertSame('License page', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_admin_without_a_key_is_redirected_to_the_license_page(): void
    {
        $this->actingAs(User::factory()->make(['id' => 1]));

        $response = (new SyncRuntimeMiddleware)->handle(
            $this->requestForRoute('admin.dashboard'),
            fn (Request $request) => response('Dashboard')
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('admin.license.index'), $response->getTargetUrl());
    }

    #[DataProvider('licenseRoutes')]
    public function test_customer_without_a_key_is_blocked_even_on_license_routes(string $routeName): void
    {
        $this->actingAs(User::factory()->make(['id' => 2]));

        try {
            (new SyncRuntimeMiddleware)->handle($this->requestForRoute($routeName), fn (Request $request) => response('Allowed'));
            $this->fail('A customer must not pass without a license key.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_guest_without_a_key_passes_through(): void
    {
        $response = (new SyncRuntimeMiddleware)->handle(
            $this->requestForRoute('home'),
            fn (Request $request) => response('Home')
        );

        $this->assertSame('Home', $response->getContent());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function licenseRoutes(): array
    {
        return [
            'license page' => ['admin.license.index'],
            'license verify' => ['admin.license.verify'],
            'license update' => ['admin.license.update'],
            'reauthenticate' => ['admin.reauthenticate'],
        ];
    }

    private function requestForRoute(string $routeName): Request
    {
        $request = Request::create('/');
        $route = (new Route('GET', '/', fn () => null))->name($routeName);
        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    }
}
