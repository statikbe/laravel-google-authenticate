---
key: v6-09-boost-skill
status: done
mode: standalone
size: small
base: feature/v6
branch: feature/v6-09-boost-skill
runner: (none)
wave: 1
---

## Goal
Rewrite the Boost skill `google-authenticate-upgrade` so an agent moves any 3.x/4.x/5.x project to 6.0 by **removing** project Google-auth code and moving settings to config.

## Context
- Plan section 10 (cleanup inventory, old vs new checklist, changed steps) — the full brief.
- File: `resources/boost/skills/google-authenticate-upgrade/SKILL.md`. Name stays, so `php artisan boost:update` delivers it.
- Audit findings to encode as cases: project A (teams on → custom team-aware listener; controller subclass only for intended redirect → delete), project B (4.x; `UserObserver::created` role grant → move to `GoogleUserRegistered` listener, keep extra `panel_admin`), project C (own listener with external sync → trim to extra work, keep default for the role; 5.0 boot guard → delete; `register_enabled=false`).
- Decisions: spatie optional (D1), teams → no default grant (D5), listener class only in published config, no env var (D11), gate gives super admin everything (D8).

## Acceptance criteria
AC1. Skill follows plan section 10 steps; each step has a detection command and an action.
AC2. Covers the three audit cases above as named patterns (not project names).
AC3. Disabling the default listener is "publish config, set `'super_admin_listener' => null`"; swapping it is "set the subclass class there".
AC4. Includes a stop-and-ask point before granting roles differently than today and before deleting a project listener with extra logic.
AC5. Flags committed Google client secrets in `.env.example` as a security finding to report, never to print.
AC6. **Absence:** no step writes a boot guard, `Gate::before` or controller/service subclass into the project.

## Out of scope / does not touch
Readme/upgrade guide (08), code. The dry run on real projects is wave 5 (user).

## Tasks
- [x] 1. Rewrite SKILL.md per section 10 + AC2–AC6 — AC1–AC6

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Review | orchestrator: names checked against config (`google-config` tag, `GOOGLE_AUTH_*` envs), no strict_types, no secrets | PASS | 3e10552, 1 file |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|

## Rulings
