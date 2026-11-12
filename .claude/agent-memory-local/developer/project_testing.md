---
name: project-testing
description: How the laravel-google-authenticate package test suite is wired (testbench + pest, sqlite memory, host runner, pint + phpstan)
metadata:
  type: project
---

- No ddev/Sail: run on host. `vendor/bin/pest` (or `composer test`). Locally PHP 8.5 resolves testbench 11 / Laravel 13 / Pest 4 / PHPUnit 12.
- Gates: `vendor/bin/pint --test`, `composer analyse` (Larastan level 6, no baseline; script sets `--memory-limit=1G`, the default 128M crashes), `vendor/bin/pest`, `composer validate --strict`.
- tests/TestCase.php builds the `users` table in `defineDatabaseMigrations()` (sqlite memory) with `provider`, `provider_id`, `remember_token`; tests/Support holds the user fixtures (User, Member, MappedUser, RoleUser, ...).
- composer.lock and vendor are gitignored.
- sqlite `Schema::getColumns` drops a varchar length after `->change()`, so column length is not observable in tests.
- A `~/.claude` guard hook blocks Write on already-committed migration files; the user approved a soft-reset of the 6.0 branch so the migration counts as new.
