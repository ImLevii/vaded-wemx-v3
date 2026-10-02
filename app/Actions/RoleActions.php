<?php

namespace App\Actions;

use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use App\Support\LicensePlanLimits;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RoleActions extends Action
{
    /**
     * Create a new role with associated permissions.
     *
     * @throws ValidationException
     */
    public static function createRoleAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
            'parent_role_id' => 'nullable|exists:roles,id',
            'super_admin' => 'required|boolean',
            'permissions' => 'required_if:super_admin,false|array',
            'permissions.*' => 'string',
        ])->validate();

        if (! empty($validatedData['parent_role_id'])) {
            $parentRole = Role::find($validatedData['parent_role_id']);
            if (! $parentRole) {
                throw ValidationException::withMessages([
                    'parent_role_id' => ['Parent role not found.'],
                ]);
            }

            $parentRolePermissions = $parentRole->getAllPermissions();
            // remove any permissions that are already in parent role
            if (isset($validatedData['permissions'])) {
                $validatedData['permissions'] = array_diff($validatedData['permissions'], $parentRolePermissions);
            }
        }

        $role = Role::create(self::omitNullValues([
            'name' => $validatedData['name'],
            'description' => $validatedData['description'] ?? null,
            'parent_id' => $validatedData['parent_role_id'] ?? null,
            'super_admin' => $validatedData['super_admin'],
        ]));

        // create role permissions if not super admin
        if (! $validatedData['super_admin'] && ! empty($validatedData['permissions'])) {
            $permissionsData = array_map(function ($permission) {
                return ['permission' => $permission];
            }, $validatedData['permissions']);

            $role->permissions()->createMany($permissionsData);
        }

        return $role;
    }

    /**
     * Update an existing role and its permissions.
     *
     * @throws ValidationException
     */
    public static function updateRoleAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'role_id' => 'required|exists:roles,id',
            'parent_role_id' => 'nullable|exists:roles,id|different:role_id',
            'name' => 'sometimes|required|string|unique:roles,name,'.($input['role_id'] ?? 'NULL').',id',
            'description' => 'nullable|string',
            'super_admin' => 'sometimes|required|boolean',
            'permissions' => 'sometimes|required_if:super_admin,false|array',
            'permissions.*' => 'string',
        ])->validate();

        $role = Role::findOrFail($validatedData['role_id']);

        if (! empty($validatedData['parent_role_id'])) {
            $parentRole = Role::find($validatedData['parent_role_id']);
            if (! $parentRole) {
                throw ValidationException::withMessages([
                    'parent_role_id' => ['Parent role not found.'],
                ]);
            }

            $parentRolePermissions = $parentRole->getAllPermissions();
            // remove any permissions that are already in parent role
            if (isset($validatedData['permissions'])) {
                $validatedData['permissions'] = array_diff($validatedData['permissions'], $parentRolePermissions);
            }
        }

        $role->update(self::omitNullValues([
            'name' => $validatedData['name'] ?? null,
            'description' => $validatedData['description'] ?? null,
            'super_admin' => $validatedData['super_admin'] ?? null,
            'parent_id' => $validatedData['parent_role_id'] ?? null,
        ]));

        // update role permissions if not super admin
        if (($validatedData['super_admin'] ?? $role->super_admin) === false && ! empty($validatedData['permissions'])) {
            // delete existing permissions
            $role->permissions()->delete();

            $permissionsData = array_map(function ($permission) {
                return ['permission' => $permission];
            }, $validatedData['permissions']);
            $role->permissions()->createMany($permissionsData);
        } elseif (array_key_exists('super_admin', $validatedData) && $validatedData['super_admin']) {
            // if super admin, remove all permissions
            $role->permissions()->delete();
        }

        return $role;
    }

    public static function assignRoleAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'role_id' => 'required|exists:roles,id',
            'user_id' => 'required|exists:users,id',
            'assigner_id' => 'required|exists:users,id',
        ])->validate();

        // if user already has role, do nothing
        $existing = RoleUser::where('role_id', $validatedData['role_id'])
            ->where('user_id', $validatedData['user_id'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'role_id' => ['User already has this role.'],
            ]);
        }

        $user = User::query()->findOrFail($validatedData['user_id']);

        if ($user->id !== 1 && ! $user->roles()->exists()) {
            $limit = LicensePlanLimits::staffAccountsLimit();
            if ($limit !== null && self::occupiedStaffAccountSeats() >= $limit) {
                throw ValidationException::withMessages([
                    'role_id' => [
                        sprintf(
                            'Your license allows %d staff account(s). Remove a staff role from another user or upgrade your license.',
                            $limit
                        ),
                    ],
                ]);
            }
        }

        // Attach role to user with assigner_id
        return RoleUser::create([
            'role_id' => $validatedData['role_id'],
            'user_id' => $validatedData['user_id'],
            'assigner_id' => $validatedData['assigner_id'],
        ]);
    }

    public static function removeRoleAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'role_id' => 'required|exists:roles,id',
            'user_id' => 'required|exists:users,id',
        ])->validate();

        // Detach role from user
        return RoleUser::where('role_id', $validatedData['role_id'])
            ->where('user_id', $validatedData['user_id'])
            ->delete();
    }

    /**
     * Seats counted toward {@see LicensePlanLimits::staffAccountsLimit()}: the initial admin (user id 1) counts as one
     * seat when that account exists, plus every other user who has at least one staff role.
     */
    private static function occupiedStaffAccountSeats(): int
    {
        $initialAdminSeat = User::query()->whereKey(1)->exists() ? 1 : 0;

        $nonPrimaryWithRoles = User::query()
            ->where('id', '!=', 1)
            ->whereHas('roles')
            ->count();

        return $initialAdminSeat + $nonPrimaryWithRoles;
    }
}
