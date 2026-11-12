<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statikbe\GoogleAuthenticate\GoogleAuthenticateServiceProvider;
use Statikbe\GoogleAuthenticate\GoogleAuthService;
use Statikbe\GoogleAuthenticate\Tests\Support\MappedUser;

function registerProvider(): void
{
    (new GoogleAuthenticateServiceProvider(app()))->register();
}

function migration(): object
{
    return require __DIR__.'/../../database/migrations/2024_01_01_000000_add_google_provider_to_users_table.php';
}

it('defaults services.google when the app sets nothing', function () {
    config(['services.google' => null]);

    registerProvider();

    expect(config('services.google'))->toBe([
        'client_id' => null,
        'client_secret' => null,
        'redirect' => '/login/google/callback',
    ]);
});

it('keeps app services.google values key by key', function () {
    config(['services.google' => ['client_id' => 'app-id', 'redirect' => '/custom']]);

    registerProvider();

    expect(config('services.google'))->toBe([
        'client_id' => 'app-id',
        'client_secret' => null,
        'redirect' => '/custom',
    ]);
});

it('binds GoogleAuthService as a singleton', function () {
    expect(app(GoogleAuthService::class))->toBe(app(GoogleAuthService::class));
});

it('adds provider columns when missing', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
    });

    migration()->up();

    expect(Schema::hasColumns('users', ['provider', 'provider_id']))->toBeTrue();
});

it('is a no-op on a table that already has the columns, also when run twice', function () {
    migration()->up();
    migration()->up();

    expect(Schema::hasColumns('users', ['provider', 'provider_id']))->toBeTrue();
});

it('adds only the missing column', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('provider')->nullable();
    });

    migration()->up();

    expect(Schema::hasColumns('users', ['provider', 'provider_id']))->toBeTrue();
});

it('fails loudly when the users table does not exist', function () {
    Schema::dropIfExists('users');

    migration()->up();
})->throws(RuntimeException::class, 'table [users] does not exist');

it('uses the table of the configured user model', function () {
    Schema::create('mapped_users', function (Blueprint $table): void {
        $table->id();
        $table->string('mail');
    });
    config(['auth.providers.users.model' => MappedUser::class]);

    migration()->up();

    expect(Schema::hasColumns('mapped_users', ['provider', 'provider_id']))->toBeTrue();
});

it('names the configured table when it is missing', function () {
    config(['auth.providers.users.model' => MappedUser::class]);

    migration()->up();
})->throws(RuntimeException::class, 'table [mapped_users] does not exist');

it('keeps pre-existing columns on rollback', function () {
    migration()->up();
    migration()->down();

    expect(Schema::hasColumns('users', ['provider', 'provider_id']))->toBeTrue();
});

function passwordIsNullable(): bool
{
    return collect(Schema::getColumns('users'))->firstWhere('name', 'password')['nullable'];
}

it('makes a NOT NULL password nullable so Google users can be created', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->string('password');
    });

    migration()->up();

    expect(passwordIsNullable())->toBeTrue();
    DB::table('users')->insert(['email' => 'jan@statik.be', 'password' => null]);
    expect(DB::table('users')->count())->toBe(1);
});

it('leaves an already nullable password alone', function () {
    expect(passwordIsNullable())->toBeTrue();

    migration()->up();

    expect(passwordIsNullable())->toBeTrue();
});

it('makes a custom-length NOT NULL password nullable without changing its type', function () {
    Schema::dropIfExists('users');
    DB::statement('create table users (id integer primary key autoincrement, email varchar(255), password varchar(100) not null)');

    migration()->up();

    $password = collect(Schema::getColumns('users'))->firstWhere('name', 'password');

    // sqlite does not keep a varchar length once the column is rebuilt, so the length itself is not observable
    // here. That it survives on MySQL/Postgres is unverified.
    expect($password['nullable'])->toBeTrue()->and($password['type'])->toStartWith('varchar');
});

function providerIdIsUnique(): bool
{
    return collect(Schema::getIndexes('users'))->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['provider_id']);
}

it('adds a unique index on provider_id when none exists and there are no duplicates', function () {
    DB::table('users')->insert([
        ['name' => 'A', 'email' => 'a@x.be', 'provider_id' => 'g-1'],
        ['name' => 'B', 'email' => 'b@x.be', 'provider_id' => null],
        ['name' => 'C', 'email' => 'c@x.be', 'provider_id' => null],
    ]);

    migration()->up();

    expect(providerIdIsUnique())->toBeTrue();
});

it('skips the unique index when one already exists, also when run twice', function () {
    Schema::table('users', fn (Blueprint $table) => $table->unique('provider_id', 'own_provider_id_unique'));

    migration()->up();
    migration()->up();

    expect(collect(Schema::getIndexes('users'))->where('unique', true)->where('columns', ['provider_id']))->toHaveCount(1);
});

it('skips the unique index without failing when provider_id holds duplicates', function () {
    DB::table('users')->insert([
        ['name' => 'A', 'email' => 'a@x.be', 'provider_id' => 'g-1'],
        ['name' => 'B', 'email' => 'b@x.be', 'provider_id' => 'g-1'],
    ]);

    migration()->up();

    expect(providerIdIsUnique())->toBeFalse()->and(DB::table('users')->count())->toBe(2);
});

it('does not touch a missing password column', function () {
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
    });

    migration()->up();

    expect(Schema::hasColumn('users', 'password'))->toBeFalse();
});

it('publishes the migration under the google-migrations tag', function () {
    $published = glob(database_path('migrations/*_add_google_provider_to_users_table.php'));
    array_map('unlink', $published);

    $this->artisan('vendor:publish', ['--tag' => 'google-migrations'])->assertSuccessful();

    $published = glob(database_path('migrations/*_add_google_provider_to_users_table.php'));
    array_map('unlink', $published);

    expect($published)->toHaveCount(1);
});

it('ships no stub migration', function () {
    expect(glob(__DIR__.'/../../database/migrations/*.stub'))->toBe([]);
});
