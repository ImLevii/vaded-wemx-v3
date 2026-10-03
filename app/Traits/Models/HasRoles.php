<?php

namespace App\Traits\Models;

use App\Models\RoleUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Trait HasRoles
 *
 * Provides relationship and helper methods
 * for a User model that has many roles.
 */
trait HasRoles
{
    public function isPrimaryAdmin(): bool
    {
        return $this->id === 1;
    }

    public function roles(): HasMany
    {
        return $this->hasMany(RoleUser::class);
    }

    /**
     * Check if the user is considered "admin".
     * Example logic:
     *   - If user ID is 1 (super admin), or
     *   - if the user is in at least one group with is_admin = true.
     */
    public function isAdmin(): bool
    {
        // If you consider user with ID=1 as super admin:
        if ($this->id === 1) {
            return true;
        }

        // Otherwise, check if there's any admin group in the loaded collection
        return false;
    }

    public function isStaff(): bool
    {
        // if user is admin return true
        if ($this->isAdmin()) {
            return true;
        }

        // if user has any role assigned return true
        return $this->roles()->exists();
    }

    public function getAllPermissions()
    {
        $permissions = [];
        foreach ($this->roles as $role) {
            $permissions[] = $role->role->getAllPermissions();
        }

        return array_unique(array_merge(...$permissions));
    }

    /**
     * Check if the user has a specific permission.
     * Assumes each group has a "permissions" relationship
     * with a "permission" attribute to compare against.
     */
    public function hasPermission(string|array $permissions): bool
    {
        // If the user is an administrator, we provide full access.
        if ($this->isAdmin()) {
            return true;
        }

        // check if user has a role that has super_admin = true
        foreach ($this->roles as $role) {
            if ($role->role->super_admin) {
                return true;
            }
        }

        $userPermissions = $this->getAllPermissions();
        if (is_array($permissions)) {
            return ! empty(array_intersect($permissions, $userPermissions));
        } elseif (is_string($permissions)) {
            return in_array($permissions, $userPermissions);
        }

        return false;
    }

    public function hasPerm(string|array $permission): bool
    {
        return $this->hasPermission($permission);
    }

    public function hasAnyPerm(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
