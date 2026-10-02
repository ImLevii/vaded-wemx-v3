@props(['option', 'selected' => null, 'showLabel' => true])
@php($required = in_array('required', explode('|', $option->rules ?? ''), true))
<div {{ $attributes->class('vh-vps-config-field') }}>
    @if($showLabel && $option->type !== 'radio')
        <label for="{{ $option->key }}-input">{{ $option->label }}@if($required)<span class="vh-vps-required" aria-hidden="true"> *</span>@endif</label>
    @endif
    @if($option->type === 'select')
        <x-theme::form.select :options="collect($option->data['options'] ?? [])->pluck('name', 'value')->all()" :default_value="$selected" id="{{ $option->key }}-input" :aria-label="$option->label" wire:model.change="config_options.{{ $option->key }}" :required="$required" />
    @elseif($option->type === 'radio')
        <x-theme::form.radio-cards :name="$option->key" :title="$showLabel ? $option->label : null" :options="$option->data['options'] ?? []" :selected="$selected" :model="'config_options.'.$option->key" :required="$required" />
    @elseif(in_array($option->type, ['text', 'email', 'password', 'number', 'range'], true))
        <x-theme::form.input :type="$option->type" :name="$option->key" :value="$selected" :placeholder="$option->placeholder ?: 'Enter '.Str::lower($option->label)" id="{{ $option->key }}-input" :aria-label="$option->label" :min="$option->data['min_value'] ?? null" :max="$option->data['max_value'] ?? null" :step="$option->data['step_value'] ?? null" wire:model.change="config_options.{{ $option->key }}" :required="$required" />
    @elseif($option->type === 'textarea')
        <x-theme::form.textarea :name="$option->key" :value="$selected" :placeholder="$option->placeholder" id="{{ $option->key }}-input" :aria-label="$option->label" wire:model.change="config_options.{{ $option->key }}" :required="$required" />
    @endif
    @error('config_options.'.$option->key)
        <div role="alert"><x-theme::form.error :text="$message" /></div>
    @else
        @if($option->description)<x-theme::form.description :text="$option->description" />@endif
    @enderror
</div>
