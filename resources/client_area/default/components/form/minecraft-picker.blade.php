@props(['option', 'groups', 'selected' => null, 'periodInDays' => 30, 'cycle' => 'Monthly'])
@php
    $selectedGroup = array_key_first($groups);
    $pingTargets = [];
    $choiceGroups = [];
    foreach ($groups as $group => $choices) {
        foreach ($choices as $index => $choice) {
            $choiceGroups[(string) $choice['value']] = $group;
            if ((string) ($choice['value'] ?? '') === (string) $selected) {
                $selectedGroup = $group;
            }
            if (! empty($choice['ping_url'])) {
                $pingTargets[$group.'-'.$index] = $choice['ping_url'];
            }
        }
    }
    $required = in_array('required', explode('|', $option->rules ?? ''), true);
@endphp
<div {{ $attributes->class('vh-minecraft-picker') }} x-data="{ group: @js($selectedGroup), choiceGroups: @js($choiceGroups) }" x-init="$watch(@js('$wire.config_options.'.$option->key), value => { group = choiceGroups[value] || group })">
    @if($groups === [])
        <p class="vh-minecraft-help">No choices are currently available.</p>
    @endif
    @if(count($groups) > 1)
        <div class="vh-minecraft-tabs flex gap-3" role="group" aria-label="{{ $option->label }} categories">
            @foreach($groups as $group => $choices)
                <button type="button" @click="group = @js($group)" :aria-pressed="group === @js($group)" aria-controls="minecraft-{{ $option->id }}-{{ $loop->index }}" @if($selectedGroup === $group) aria-pressed="true" @else aria-pressed="false" @endif>{{ $group }}</button>
            @endforeach
        </div>
    @endif
    <div x-data="minecraftLocationPing(@js($pingTargets))">
        @if($pingTargets !== [])
            <div class="vh-minecraft-ping-bar"><span>Unsure which location is best for you?</span><button type="button" class="vh-text-link" @click="testLocations()" :disabled="testing" x-text="testing ? 'Testing locations…' : 'Test my ping'">Test my ping</button><span class="sr-only" role="status" x-text="testing ? 'Testing location latency' : ''"></span></div>
        @endif
        @foreach($groups as $group => $choices)
            <fieldset id="minecraft-{{ $option->id }}-{{ $loop->index }}" x-show="group === @js($group)" @if($selectedGroup !== $group) x-cloak @endif>
                <legend class="sr-only">{{ $option->label }}: {{ $group }}</legend>
                <div class="grid grid-cols-1 gap-3 {{ $group === 'Locations' ? 'md:grid-cols-2' : 'sm:grid-cols-2 xl:grid-cols-3' }}">
                    @foreach($choices as $index => $choice)
                        @php
                            $id = 'minecraft-option-'.$option->id.'-'.$group.'-'.$index;
                            $pingKey = $group.'-'.$index;
                            $surcharge = max(0, (float) ($choice['daily_price'] ?? 0)) * ($periodInDays ?: ($option->onetime_day_equivalent ?? 365));
                        @endphp
                        <div wire:key="{{ $id }}">
                            <input type="radio" id="{{ $id }}" name="minecraft-{{ $option->key }}" value="{{ $choice['value'] }}" wire:model.change="config_options.{{ $option->key }}" class="sr-only" @checked((string) $selected === (string) $choice['value']) @required($required)>
                            <label for="{{ $id }}" class="vh-minecraft-choice flex items-center gap-3">
                                @if(!empty($choice['icon_url']))<img src="{{ $choice['icon_url'] }}" alt="" width="36" height="36" loading="lazy">@elseif(!empty($choice['flag']))<span class="vh-minecraft-flag" aria-hidden="true">{{ $choice['flag'] }}</span>@else<x-theme::icon :name="$group === 'Locations' ? 'network' : 'cube'" />@endif
                                <span class="vh-minecraft-choice-copy"><strong>{{ $choice['name'] ?? $choice['value'] }}</strong>@if(!empty($choice['description']))<small>{{ $choice['description'] }}</small>@endif
                                    @if(!empty($choice['premium_only']))<small>Premium only</small>@endif
                                    @if($surcharge > 0)<small>+ {{ price($surcharge) }} / {{ $cycle }}</small>@endif
                                    @if($group === 'Locations')<small aria-live="polite" x-text="pingLabel(@js($pingKey))">Ping not tested</small>@endif
                                </span>
                                @if($group === 'Locations')<span class="vh-minecraft-badge" x-show="best === @js($pingKey)" x-cloak>Best ping</span>@endif
                            </label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>
    @error('config_options.'.$option->key)<div role="alert"><x-theme::form.error :text="$message" /></div>@else @if($option->description)<x-theme::form.description :text="$option->description" />@endif @enderror
</div>
