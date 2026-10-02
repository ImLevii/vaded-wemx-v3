<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\ServerConnection;
use Exception;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PterodactylNodeSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_checkout_matches_the_location_instead_of_the_node_id(): void
    {
        Http::fake(['*/nodes/deployable*' => Http::response(['data' => [$this->node(20, 7, false)]])]);

        Server::eventAddToCart($this->package(), ['memory_limit' => '2.5']);

        Http::assertSent(fn (Request $request) => $request['location_ids'] === [7]
            && $request['memory'] === 2560 && $request['disk'] === 10240);
        Http::assertSentCount(1);
    }

    public function test_a_matching_node_id_in_another_location_is_rejected(): void
    {
        Http::fake(['*/nodes/deployable*' => Http::response(['data' => [$this->node(7, 99, false)]])]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('in the selected location');

        Server::eventAddToCart($this->package());
    }

    public function test_checkout_continues_past_nodes_without_free_allocations(): void
    {
        Http::fake(['*/nodes/deployable*' => Http::response(['data' => [
            $this->node(20, 7, true), $this->node(21, 7, false),
        ]])]);

        Server::eventAddToCart($this->package());

        Http::assertSentCount(1);
    }

    public function test_checkout_searches_later_pages(): void
    {
        Http::fake(['*/nodes/deployable*' => Http::sequence()
            ->push(['data' => [$this->node(20, 7, true)], 'meta' => ['pagination' => ['total_pages' => 2]]])
            ->push(['data' => [$this->node(21, 7, false)], 'meta' => ['pagination' => ['total_pages' => 2]]])]);

        Server::eventAddToCart($this->package());

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['page'] === 2 && $request['location_ids'] === [7]);
    }

    public function test_exhausted_allocations_are_reported_after_searching_every_page(): void
    {
        Http::fake(['*/nodes/deployable*' => Http::sequence()
            ->push(['data' => [$this->node(20, 7, true)], 'meta' => ['pagination' => ['total_pages' => 2]]])
            ->push(['data' => [$this->node(21, 7, true)], 'meta' => ['pagination' => ['total_pages' => 2]]])]);

        try {
            Server::eventAddToCart($this->package());
            $this->fail('Checkout must reject nodes with no free allocation.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('free allocation on any eligible node', $exception->getMessage());
            Http::assertSentCount(2);
        }
    }

    public function test_no_deployable_nodes_reports_the_location_and_capacity_requirements(): void
    {
        Http::fake(['*/nodes/deployable*' => Http::response(['data' => []])]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Check the Location ID and available memory and disk');

        Server::eventAddToCart($this->package());
    }

    public function test_a_location_slug_is_rejected_before_contacting_the_panel(): void
    {
        try {
            Server::eventAddToCart($this->package(), ['location_id' => 'vps-starter']);
            $this->fail('Location slugs must not be accepted as IDs.');
        } catch (Exception $exception) {
            $this->assertStringContainsString('positive numeric ID', $exception->getMessage());
            Http::assertNothingSent();
        }
    }

    public function test_package_location_validation_requires_a_positive_integer(): void
    {
        $package = $this->package();
        $field = collect((new Server)->setPackageConfig($package, $package->serverConnection))->firstWhere('key', 'location_id');

        foreach (['vps-starter', 0, -1, 1.5, ''] as $locationId) {
            $this->assertTrue(Validator::make(['location_id' => $locationId], ['location_id' => $field['rules']])->fails());
        }

        $this->assertTrue(Validator::make(['location_id' => '7'], ['location_id' => $field['rules']])->passes());
        Http::assertNothingSent();
    }

    public function test_panel_errors_are_not_reported_as_insufficient_capacity(): void
    {
        Http::fake(['*/nodes/deployable*' => Http::response([], 403)]);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('status code: 403');

        Server::eventAddToCart($this->package());
    }

    protected function package(): Package
    {
        $package = new Package(['data' => ['location_id' => '7', 'memory_limit' => '2', 'disk_limit' => '10']]);
        $package->setRelation('serverConnection', new ServerConnection(['config' => [
            'hostname' => 'https://panel.example.test', 'api_key' => 'ptla_test_key',
        ]]));

        return $package;
    }

    /** @return array{attributes: array<string, mixed>} */
    protected function node(int $id, int $locationId, bool $assigned): array
    {
        return ['attributes' => [
            'id' => $id,
            'location_id' => $locationId,
            'relationships' => ['allocations' => ['data' => [['attributes' => [
                'id' => $id * 10, 'assigned' => $assigned,
            ]]]]],
        ]];
    }
}
