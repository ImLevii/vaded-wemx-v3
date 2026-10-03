<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerServerCredentials
{
    public function capture(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The provided password does not match your current password.',
            ]);
        }

        $user->forceFill(['server_password' => $password])->saveQuietly();
    }

    public function password(User $user): ?string
    {
        $password = $user->server_password;

        return is_string($password) && Hash::check($password, $user->password) ? $password : null;
    }
}
