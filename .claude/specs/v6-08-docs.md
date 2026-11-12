---
key: v6-08-docs
status: done
mode: standalone
size: small
base: feature/v6
branch: feature/v6-08-docs
runner: (none)
wave: 1
---

## Goal
Readme, upgrade guide and changelog describe 6.0 as specified, written from the plan and these specs (not from code), so docs can be built in parallel with code.

## Context
- Plan sections 1, 5, 6, 6.1, 8, D1–D11. Shared contracts in `v6-PROGRESS.md`.
- Breaking-change list (13 items) agreed in conversation 2026-11-12; plan section 8 misses: optional spatie (D1), listener on by default (D4), missing `HasRoles` throws (D5), views + `google-views` tag removed, `guest` on routes, `redirect()->intended()`.
- Real upgrade cases: project A (teams on, controller subclass for intended redirect), project B (4.x, observer granting roles on `User::created`), project C (own listener + external sync, 5.0 boot guard).

## Acceptance criteria
AC1. `readme.md`: 3-step install (section 1), config reference (section 5), "Super admin role" (spatie optional, teams unsupported → custom listener, gate sees everything), "Custom listener" (section 6.1 incl. `assignRole()` override and `super_admin_listener` class config, facade `isAllowedDomain()`), listener rules list.
AC2. `upgrade-guide.md`: a "5.x → 6.0" section listing every breaking change (the 13 items) with the action per item, plus "4.x → 6.0" notes (project B-style observer → listener). Mentions route names unchanged.
AC3. `changelog.md`: 6.0.0 entry (Added / Changed / Removed / Breaking).
AC4. Plan section 8 updated with the missing items listed in Context; no other plan sections changed.
AC5. **Absence:** no env var for `super_admin_listener` anywhere in docs; no `declare(strict_types=1)` in code samples.

## Out of scope / does not touch
Boost skill (09), code.

## Tasks
- [x] 1. Plan section 8 completion — AC4
- [x] 2. Upgrade guide 6.0 section — AC2
- [x] 3. Readme rewrite — AC1, AC5
- [x] 4. Changelog — AC3

## Gate Ledger
| Gate | Command | Result | Evidence |
|---|---|---|---|
| Review | orchestrator: open questions answered, round 1 added migration + email-change notes | PASS (re-check in wave 4) | 858204b..bc37228, merge 2e18a3c |

## Change Log
| Date | Change | Why | Approved by |
|---|---|---|---|

## Rulings
