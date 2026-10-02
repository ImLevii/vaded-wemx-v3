<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\Gateway;
use Illuminate\View\View;
use Livewire\Attributes\Url;

new class extends Component {
    #[Url]
    public $gateway;

    public $webhook_id;

    public $display_name;

    public $display_description;

    public $icon;

    public $is_enabled = true;

    public $is_staff_only = false;

    public array $gateways = [];

    public array $gatewayConfig = [];

    public array $config = [];

    public function mount()
    {
        $this->gateways = Gateway::where('status', 'enabled')->get()->pluck('name', 'identifier')->toArray();
    }

    public function createConfig()
    {
        \App\Models\GatewayConfig::actions()->createGatewayConfigAsAdmin([
            'extension_identifier' => $this->gateway,
            'webhook_id' => ($this->activeGateway->hasWebhook() OR $this->activeGateway->hasCallback()) ? $this->webhook_id : null,
            'display_name' => $this->display_name,
            'display_description' => $this->display_description,
            'icon' => $this->icon,
            'config' => $this->config,
            'is_enabled' => $this->is_enabled,
            'is_staff_only' => $this->is_staff_only,
        ]);

        $this->redirect(route('admin.gateways.configs.index'));
    }

    public function rendering(View $view): void
    {
        if ($this->gateway) {
            $gateway = Gateway::where('identifier', $this->gateway)->first();

            $this->gatewayConfig = $gateway->getConfig()->toArray();

            if (!$this->display_name) {
                $this->display_name = $gateway->name;
                $this->webhook_id = Str::slug($this->display_name, '_');
            }

            if (!$this->display_description) {
                $this->display_description = $this->activeGateway->functions()->gatewayDescription ?? '';
            }

            if (!$this->icon) {
                $this->icon = $this->activeGateway->getGatewayIcon();
            }
        }

        if ($this->display_name AND !$this->webhook_id) {
            $this->webhook_id = Str::slug($this->display_name, '_');
        }
    }

    // when the gateway is changed, reset the config array
    public function updatedGateway($value)
    {
        $this->config = [];
        $this->display_name = '';
        $this->display_description = '';
        $this->webhook_id = '';
        $this->icon = '';
    }

    public function setGateway($identifier)
    {
        $this->gateway = $identifier;
    }

    #[Computed]
    public function activeGateway(): ?Gateway
    {
        return Gateway::where('identifier', $this->gateway)->first();
    }
}
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ __('messages.create_gateway_config') }}</h3>
    </div>
    <div class="card-body">
        <div class="mb-3 row">
            <label class="col-3 col-form-label" for="server-input">{{ __('messages.select_gateway') }}</label>
            <div class="col">
                <select class="form-select @error('gateway') is-invalid @enderror" wire:model.change="gateway"
                        id="server-input">
                    <option value="">{{ __('messages.select_server') }}</option>
                    @foreach($gateways as $key => $installedGateway)
                        <option value="{{ $key }}">{{ $installedGateway }}</option>
                    @endforeach
                </select>
                @error('gateway')
                <x-admin::form.error :message="$message"/>
                @else
                    <small class="form-hint">Select the Gateway for which you want to make the config</small>
                    @enderror
            </div>
        </div>
        @if($gateway)
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="display_name">{{ __('messages.display_name') }}</label>
                <div class="col">
                    <input type="text" wire:model.change="display_name" class="form-control"
                           aria-describedby="display_name" id="display_name" placeholder="Display Name"/>
                    @error('display_name')
                    <x-admin::form.error :message="$message"/>
                    @else
                        <small class="form-hint">
                            {{ __('messages.display_name_desc') }}
                        </small>
                        @enderror
                </div>
            </div>
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="display_description-input">Display Description</label>
                <div class="col">
                    <textarea class="form-control @error('display_description') is-invalid @enderror" wire:model="display_description" id="display_description-input" rows="2" placeholder="Content.."></textarea>
                    @error('display_description')
                        <x-admin::form.error :message="$message" />
                    @else
                        <small class="form-hint">Enter the description as displayed to customers</small>
                    @enderror
                </div>
            </div>
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="icon-input">{{ __('messages.icon') }} (Optional)</label>
                <div class="col">
                    <div>
                        @if($icon)
                            <img src="{{ $icon }}" class="avatar avatar-xl mb-3" alt="category icon">
                        @endif

                        <div>
                            <input type="text" wire:model.change="icon" class="form-control mb-1" aria-describedby="icon_url-input" id="icon_url-input" placeholder="{{ __('messages.icon_url') }}">
                            @error('icon')
                            <x-admin::form.error :message="$message" />
                            @else
                                <small class="form-hint mb-3">
                                    The icon URL for this gateway as displayed to customers
                                </small>
                                @enderror
                                <x-admin::button href="{{ route('admin.images.index') }}" target="_blank" rel="opener" onclick="window.imagePickerTarget = document.getElementById('icon_url-input')" class="mb-2">
                                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-file-upload"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M12 11v6" /><path d="M9.5 13.5l2.5 -2.5l2.5 2.5" /></svg>
                                    Upload Images
                                </x-admin::button>
                        </div>
                    </div>
                </div>
            </div>
            @if($this->activeGateway->hasWebhook() OR $this->activeGateway->hasCallback())
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="webhook_id">Webhook ID</label>
                <div class="col">
                    <input type="text" wire:model="webhook_id"
                           class="form-control @error('webhook_id') is-invalid @enderror" aria-describedby="webhook_id"
                           id="webhook_id" placeholder="Webhook ID"/>
                    @error('webhook_id')
                    <x-admin::form.error :message="$message"/>
                    @else
                        <small class="form-hint">Unique webhook identifier used to listen for webhooks from this gateway
                            for example <code>paypal-gateway-live</code></small>
                        @enderror
                </div>
            </div>
            @endif
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="is_enabled">{{ __('messages.enabled') }}</label>
                <div class="col">
                    <label class="form-check form-switch">
                        <input class="form-check-input" wire:model="is_enabled" type="checkbox"/>
                        <span class="form-check-label">Is this gateway enabled?</span>
                    </label>
                    @error('is_enabled')
                    <x-admin::form.error :message="$message"/>
                    @enderror
                </div>
            </div>
            <div class="mb-3 row">
                <label class="col-3 col-form-label" for="is_staff_only">Staff Only</label>
                <div class="col">
                    <label class="form-check form-switch">
                        <input class="form-check-input" wire:model="is_staff_only" type="checkbox"/>
                        <span class="form-check-label">
                            Only staff members with the permission 'use-staff-gateways' can use this gateway at checkout.
                        </span>
                    </label>
                    @error('is_staff_only')
                        <x-admin::form.error :message="$message"/>
                    @enderror
                </div>
            </div>
            <div class="hr"></div>
            @foreach($this->gatewayConfig as $key => $config)
                @if($config['type'] === 'select')
                    <div class="mb-3 row">
                        <label class="col-3 col-form-label" for="config-{{ $key }}-input">{{ $config['label'] }}</label>
                        <div class="col">
                            <select class="form-select @error($key) is-invalid @enderror" wire:model="config.{{ $key }}"
                                    id="config-{{ $key }}-input">
                                <option value="">{{ __('messages.select') }}</option>
                                @foreach($config['options'] ?? [] as $optionKey => $option)
                                    <option value="{{ $optionKey }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error($key)
                            <x-admin::form.error :message="$message"/>
                            @else
                                <small class="form-hint">{{ $config['description'] ?? '' }}</small>
                                @enderror
                        </div>
                    </div>
                @else
                    <div class="mb-3 row">
                        <label class="col-3 col-form-label required"
                               for="config-{{ $key }}-input">{{ $config['label'] }}</label>
                        <div class="col">
                            <input type="{{ $config['type'] ?? 'text' }}" value="{{ $config['default_value'] ?? '' }}"
                                   wire:model="config.{{ $key }}" class="form-control @error($key) is-invalid @enderror"
                                   aria-describedby="config-{{ $key }}-input" id="config-{{ $key }}-input"
                                   placeholder="{{ $config['placeholder'] ?? $config['label'] }}"/>
                            @error($key)
                                <x-admin::form.error :message="$message"/>
                            @else
                                <small class="form-hint">{{ $config['description'] ?? '' }}</small>
                            @enderror
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>
    <div class="card-footer text-end">
        <button type="button" wire:click="createConfig" class="btn btn-primary">{{ __('messages.create') }}</button>
    </div>
</div>

