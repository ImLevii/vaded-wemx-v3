<?php

namespace App\Actions;

use App\Models\ServerConnection;
use App\Support\LicensePlanLimits;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServerConnectionActions extends Action
{
    /**
     * Create a new Server Connection
     *
     * @throws ValidationException
     */
    public static function createServerConnectionAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'alias' => ['required', 'string', 'max:255', 'unique:server_connections,alias'],
            'short_description' => ['nullable', 'string'],
            'extension_identifier' => ['required', 'string', 'exists:extensions,identifier'],
            'config' => ['nullable', 'array'],
            'status' => ['required', 'string', 'in:healthy,unavailable,unknown'],
            'receive_alerts' => ['nullable', 'boolean'],
            'alert_email' => ['required_if:receive_alerts,true', 'email'],
            'prevent_purchasing' => ['nullable', 'boolean'],
        ])->validate();

        LicensePlanLimits::assertCanCreateServerConnections(1, 'alias');

        return ServerConnection::create(self::omitNullValues($validatedData));
    }

    /**
     * Update a server connection
     *
     * @throws ValidationException
     */
    public static function updateServerConnectionAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'connection_id' => ['required', 'integer', 'exists:server_connections,id'],
            'alias' => ['sometimes', 'required', 'string', 'max:255', 'unique:server_connections,alias'.$input['connection_id'] ?? null],
            'short_description' => ['nullable', 'string'],
            'config' => ['nullable', 'array'],
            'status' => ['sometimes', 'required', 'string', 'in:healthy,unavailable,unknown'],
            'receive_alerts' => ['nullable', 'boolean'],
            'alert_email' => ['required_if:receive_alerts,true', 'email'],
            'prevent_purchasing' => ['nullable', 'boolean'],
        ])->validate();

        $connection = ServerConnection::find($validatedData['connection_id']);

        if (! $connection) {
            throw ValidationException::withMessages([
                'connection_id' => 'Connection not found',
            ]);
        }

        unset($validatedData['connection_id']);

        return $connection->update(self::omitNullValues($validatedData));
    }
}
