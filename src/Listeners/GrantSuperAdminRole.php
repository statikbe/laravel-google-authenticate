<?php

namespace Statikbe\GoogleAuthenticate\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LogicException;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;
use Statikbe\GoogleAuthenticate\Events\GoogleUserRegistered;

/**
 * Grants the configured super admin role to a brand-new user from an allowed domain. Deliberately not
 * ShouldQueue: it must run in the login transaction, so a failure rolls the new user back.
 */
class GrantSuperAdminRole
{
    public function handle(GoogleUserRegistered $event): void
    {
        $roleName = config('google-authenticate.super_admin_role');

        if (! $roleName || ! $event->isFromAllowedDomain()) {
            return;
        }

        $this->assignRole($event->user, $roleName);
    }

    /**
     * Override this to use another role library; handle() keeps the domain check.
     */
    protected function assignRole(Model $user, string $roleName): void
    {
        // Spatie is optional, and teams are not supported: team projects write a custom listener.
        if (! $this->permissionsAvailable()) {
            return;
        }

        if (config('permission.teams')) {
            Log::warning('google-authenticate: super admin role not granted, spatie teams are on. Write a custom listener.', ['role' => $roleName]);

            return;
        }

        if (! method_exists($user, 'assignRole')) {
            throw new LogicException($user::class.' must use Spatie\Permission\Traits\HasRoles for the Google super admin role.');
        }

        $roleClass = app(PermissionRegistrar::class)->getRoleClass();
        $user->assignRole($roleClass::findOrCreate($roleName, Guard::getDefaultName($user)));
    }

    protected function permissionsAvailable(): bool
    {
        return class_exists(PermissionRegistrar::class);
    }
}
