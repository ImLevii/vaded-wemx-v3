<?php

namespace Tests\Feature;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CurrencyDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['USD' => true, 'EUR' => true, 'GBP' => false] as $code => $active) {
            Currency::query()->updateOrCreate(['currency' => $code], [
                'display_name' => $code.' currency',
                'format' => '1,0.00',
                'market_rate' => 1,
                'is_active' => $active,
            ]);
        }
    }

    public function test_selector_renders_active_currency_flags_and_selected_currency(): void
    {
        session()->put('currency', 'EUR');

        Volt::test(client_view_path('livewire.widgets.currency-dropdown'))
            ->assertSee('Currency: EUR')
            ->assertSee('flags/eu.svg', false)
            ->assertSee('flags/us.svg', false)
            ->assertDontSee('GBP currency');
    }

    public function test_selecting_an_active_currency_persists_and_refreshes_the_page(): void
    {
        session()->put('currency', 'USD');

        Volt::test(client_view_path('livewire.widgets.currency-dropdown'))
            ->call('setCurrency', 'EUR')
            ->assertRedirect(route('dashboard'));

        $this->assertSame('EUR', session('currency'));
    }

    public function test_inactive_and_unknown_currencies_cannot_be_selected(): void
    {
        session()->put('currency', 'USD');

        foreach (['GBP', 'INVALID'] as $code) {
            Volt::test(client_view_path('livewire.widgets.currency-dropdown'))
                ->call('setCurrency', $code)
                ->assertNoRedirect();

            $this->assertSame('USD', session('currency'));
        }
    }

    public function test_custom_currencies_have_a_globe_fallback(): void
    {
        Currency::query()->create([
            'currency' => 'XYZ', 'display_name' => 'Custom currency',
            'format' => '1,0.00', 'market_rate' => 1, 'is_active' => true,
        ]);
        session()->put('currency', 'XYZ');

        Volt::test(client_view_path('livewire.widgets.currency-dropdown'))
            ->assertSee('Currency: XYZ')
            ->assertSee('vh-currency-globe', false)
            ->assertDontSee('flags/xyz.svg', false);
    }
}
