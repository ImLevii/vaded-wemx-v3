@props(['name', 'groups', 'model', 'selected' => null, 'required' => false, 'periodInDays' => 30, 'cycle' => 'Monthly'])
@php
    $selectedGroup = array_key_first($groups);
    foreach ($groups as $key => $group) {
        foreach ($group['options'] as $choice) {
            if ((string) ($choice['value'] ?? '') === (string) $selected) {
                $selectedGroup = $key;
                break 2;
            }
        }
    }
@endphp
<div {{ $attributes->class('vh-vps-os-picker') }} x-data="{ group: @js($selectedGroup) }">
    <div class="vh-vps-os-tabs flex flex-wrap gap-1" role="group" aria-label="Operating system families">
        @foreach($groups as $key => $group)
            <button type="button" @click="group = '{{ $key }}'" :aria-pressed="group === '{{ $key }}'" aria-controls="{{ $name }}-{{ $key }}-versions" @if($key === $selectedGroup) aria-pressed="true" @else aria-pressed="false" @endif>
                @if($group['icon'])<img src="{{ asset($group['icon']) }}" alt="" width="20" height="20">@else<x-theme::icon name="console" />@endif
                {{ $group['name'] }}
            </button>
        @endforeach
    </div>
    @foreach($groups as $key => $group)
        <fieldset id="{{ $name }}-{{ $key }}-versions" class="vh-vps-os-versions grid grid-cols-1 gap-3 md:grid-cols-2" x-show="group === '{{ $key }}'" @if($key !== $selectedGroup) x-cloak @endif>
            <legend class="sr-only">{{ $group['name'] }} versions</legend>
            @foreach($group['options'] as $index => $choice)
                @php($id = $name.'-'.$key.'-'.$index)
                <div wire:key="os-{{ $id }}">
                    <input class="sr-only" type="radio" id="{{ $id }}" name="{{ $name }}" value="{{ $choice['value'] }}" wire:model.change="{{ $model }}" @checked((string) $selected === (string) $choice['value']) @required($required)>
                    <label for="{{ $id }}" class="vh-vps-os-card flex items-center gap-5">
                        @if($choice['icon_url'] ?? $group['icon'])<img src="{{ $choice['icon_url'] ?? asset($group['icon']) }}" alt="" width="40" height="40">@else<x-theme::icon name="console" />@endif
                        <span><strong>{{ $choice['name'] ?? $choice['value'] }}</strong>
                            @if($choice['description'] ?? null)<span class="vh-vps-os-description">{{ $choice['description'] }}</span>@endif
                            @if((float) ($choice['daily_price'] ?? 0) > 0)<small>+ {{ price((float) $choice['daily_price'] * $periodInDays) }} / {{ $cycle }}</small>@endif
                        </span>
                        <span class="vh-vps-os-check" aria-hidden="true"><x-theme::icon name="check" /></span>
                    </label>
                </div>
            @endforeach
        </fieldset>
    @endforeach
</div>
