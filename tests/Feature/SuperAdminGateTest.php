<?php

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider;
use Statikbe\GoogleAuthenticate\Tests\Support\RoleUser;
use Statikbe\GoogleAuthenticate\Tests\Support\User;

function roleUser(?string $role = null): RoleUser
{
    $user = RoleUser::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be']);

    if ($role) {
        $user->assignRole(Role::findOrCreate($role));
    }

    return $user;
}

it('lets the super admin role pass any ability, even one a policy denies', function () {
    Gate::define('publish', fn ($user) => false);

    $user = roleUser('super_admin');

    expect(Gate::forUser($user)->allows('publish'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('undefined-ability'))->toBeTrue();
});

it('returns null, not false, so users without the role get the normal checks', function () {
    Gate::define('publish', fn ($user) => false);
    Gate::define('read', fn ($user) => true);

    $user = roleUser('editor');

    expect(Gate::forUser($user)->allows('publish'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('read'))->toBeTrue();
});

it('does not pass the gate for a same-named role under another guard', function () {
    Gate::define('publish', fn ($user) => false);

    $user = roleUser();
    $role = Role::create(['name' => 'super_admin', 'guard_name' => 'api']);
    // assignRole refuses a guard mismatch, so write the pivot row as a mis-seeded app would.
    DB::table('model_has_roles')->insert([
        'role_id' => $role->getKey(),
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
    ]);

    expect(Gate::forUser($user->fresh())->allows('publish'))->toBeFalse();
});

it('does not affect a user model without hasRole', function () {
    Gate::define('read', fn ($user) => true);
    Gate::define('publish', fn ($user) => false);

    $user = User::forceCreate(['name' => 'Jan', 'email' => 'jan@statik.be']);

    expect(Gate::forUser($user)->allows('read'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('publish'))->toBeFalse();
});

it('registers no gate when super_admin_gate is false', function () {
    putenv('GOOGLE_AUTH_SUPER_ADMIN_GATE=false');

    try {
        $this->refreshApplication();
        $this->defineDatabaseMigrations();

        expect(config('google-authenticate.super_admin_gate'))->toBeFalse()
            ->and(Gate::forUser(roleUser('super_admin'))->allows('undefined-ability'))->toBeFalse();
    } finally {
        putenv('GOOGLE_AUTH_SUPER_ADMIN_GATE');
    }
});

it('registers no gate when super_admin_role is empty', function () {
    putenv('GOOGLE_AUTH_SUPER_ADMIN_ROLE=');

    try {
        $this->refreshApplication();
        $this->defineDatabaseMigrations();

        expect(Gate::forUser(roleUser('super_admin'))->allows('undefined-ability'))->toBeFalse();
    } finally {
        putenv('GOOGLE_AUTH_SUPER_ADMIN_ROLE');
    }
});

it('uses the model own hasRole without touching spatie classes when spatie is absent', function () {
    Gate::define('publish', fn ($user) => false);

    $user = new class extends Illuminate\Foundation\Auth\User
    {
        public function hasRole(string $role): bool
        {
            // A second argument would mean the spatie guard call was made.
            return func_num_args() === 1 && $role === 'super_admin';
        }
    };

    // spatie is a require-dev here, so "absent" is simulated through the overridable check.
    (new class(app()) extends GoogleAuthenticateServiceProvider
    {
        protected function permissionsAvailable(): bool
        {
            return false;
        }

        public function gate(): void
        {
            $this->registerSuperAdminGate();
        }
    })->gate();

    expect(Gate::forUser($user)->allows('publish'))->toBeTrue();
});

it('does not throw for a guest and denies when the super admin gate is on', function () {
    // Gate::before is not called for guests while its first parameter is untyped, so hasRole() is never reached.
    expect(config('google-authenticate.super_admin_gate'))->toBeTrue();

    expect(Gate::allows('anything'))->toBeFalse();
});
