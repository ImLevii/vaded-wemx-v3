@props(['id', 'autocomplete' => 'current-password'])
<div class="vh-password-field" x-data="{ visible: false }">
    <x-theme::form.input :id="$id" type="password" x-bind:type="visible ? 'text' : 'password'" :autocomplete="$autocomplete" {{ $attributes }} />
    <button type="button" @click="visible = !visible" :aria-pressed="visible" aria-controls="{{ $id }}" :aria-label="visible ? 'Hide password' : 'Show password'" x-text="visible ? 'Hide' : 'Show'">Show</button>
</div>
