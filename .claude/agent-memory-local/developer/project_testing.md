---
name: project-testing
description: How the laravel-google-authenticate package test suite is wired (testbench + pest, App\Models\User alias, no runner container)
metadata:
  type: project
---

- No ddev/Sail: run on host. `vendor/bin/pest` (or `composer test`). PHP 8.5 locally resolves testbench 11 / Laravel 13 / Pest 4 / PHPUnit 12.
- Controller return type is `App\Models\User`; tests/TestCase.php `class_alias`es tests/Support/User to it. Keep that when adding tests.
- Test users table needs `remember_token` (controller calls `Auth::login($user, true)`).
- composer.lock and vendor are gitignored. `.gitignore` originally had no trailing newline — appending with `>>` glues lines.
- No pint/phpstan installed in the package (StyleCI via `_styleci.yml`).
