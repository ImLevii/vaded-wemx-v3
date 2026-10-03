<?php

namespace App\Actions;

use App\Mail\EmailTheme;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmailActions extends Action
{
    public function sendUserEmail(array $input): ?Email
    {
        $input = EmailTemplate::compose($input, User::query()->find($input['user_id'] ?? null));
        if ($input === null) {
            return null;
        }
        $validated = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'token' => ['nullable', 'string', 'max:255'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'mailable_type' => ['nullable', 'string', 'max:255'],
            'mailable_id' => ['nullable'],
            'from' => ['nullable', 'email'],
            'to' => ['nullable', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array'],
            'table' => ['nullable', 'array'],
            'table.columns' => ['required_if:table,true', 'array'],
            'table.rows' => ['required_if:table,true', 'array'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'required_with:button_text'],
            'attachments' => ['nullable', 'array'],
            'theme' => ['nullable', 'string', Rule::in(array_keys(EmailTheme::options()))],
            'display' => ['nullable', 'boolean'],
        ])->validate();

        $user = User::find($validated['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        if (! isset($validated['to'])) {
            $validated['to'] = $user->email;
        }

        // if theme is not set, set to default
        if (! isset($validated['theme'])) {
            $validated['theme'] = EmailTheme::default()->slug;
        }

        // if display is not set, set to true
        if (! isset($validated['display'])) {
            $validated['display'] = true;
        }

        return Email::create(self::omitNullValues($validated));
    }

    public function sendEmailToAddress(array $input): Email
    {
        $validated = Validator::make($input, [
            'identifier' => ['nullable', 'string', 'max:255', 'required_with:cooldown'],
            'from' => ['nullable', 'email'],
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array'],
            'button_text' => ['nullable', 'string', 'max:255', 'required_with:button_url'],
            'button_url' => ['nullable', 'url', 'required_with:button_text'],
            'attachments' => ['nullable', 'array'],
            'theme' => ['nullable', 'string', Rule::in(array_keys(EmailTheme::options()))],
            'display' => ['nullable', 'boolean'],
            'cooldown' => ['nullable', 'integer'],
        ])->validate();

        // Check if identifier exists and apply cooldown logic
        if (isset($validated['cooldown'])) {
            $lastEmail = Email::where('identifier', $validated['identifier'])
                ->where('created_at', '>', now()->subMinutes($validated['cooldown']))
                ->latest()
                ->first();

            if ($lastEmail) {
                return $lastEmail; // Cooldown in effect, email not sent
            }
        }

        return Email::create(self::omitNullValues($validated));
    }
}
