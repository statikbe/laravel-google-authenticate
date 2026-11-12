<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Idempotent: apps that published the 5.x migration (or own the columns) already have them.
return new class extends Migration
{
    public function up(): void
    {
        $users = $this->usersTable();

        // Fail loudly: returning here would record the migration as run and never add the columns.
        if (! Schema::hasTable($users)) {
            throw new RuntimeException("google-authenticate: table [{$users}] does not exist. Run this migration after the one that creates it, or point auth.providers.users.model at the right model.");
        }

        foreach (['provider', 'provider_id'] as $column) {
            if (! Schema::hasColumn($users, $column)) {
                Schema::table($users, fn (Blueprint $table) => $table->string($column)->nullable());
            }
        }

        // Google users are created without a password, so a fresh app's NOT NULL column must allow null.
        $password = collect(Schema::getColumns($users))->firstWhere('name', 'password');

        if ($password !== null && ! $password['nullable']) {
            // change() redefines the column, so pass the existing length or a custom one is reset to 255.
            $length = preg_match('/\((\d+)\)/', (string) $password['type'], $matches) === 1 ? (int) $matches[1] : 255;

            Schema::table($users, fn (Blueprint $table) => $table->string('password', $length)->nullable()->change());
        }

        $this->addProviderIdIndex($users);
    }

    public function down(): void
    {
        // Deliberate no-op: the app may have created these columns itself, and dropping them would wipe every Google link.
    }

    private function usersTable(): string
    {
        $model = config('auth.providers.users.model');

        return is_string($model) && is_subclass_of($model, Model::class)
            ? (new $model)->getTable()
            : 'users';
    }

    /**
     * Stops a double callback from creating two rows for one Google account. Skipped, never failed, when the
     * app already has such an index or holds duplicates: the migration cannot decide which row to keep.
     */
    private function addProviderIdIndex(string $users): void
    {
        $indexed = collect(Schema::getIndexes($users))
            ->contains(fn (array $index) => $index['unique'] && in_array('provider_id', $index['columns'], true));

        if ($indexed) {
            return;
        }

        $hasDuplicates = DB::table($users)
              ->select('provider_id')
            ->whereNotNull('provider_id')
            ->groupBy('provider_id')
            ->havingRaw('count(*) > 1')
            ->exists();

        if (! $hasDuplicates) {
            Schema::table($users, fn (Blueprint $table) => $table->unique('provider_id'));
        }
    }
};
