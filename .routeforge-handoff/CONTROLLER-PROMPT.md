# Controller prompt — schema-modernization-for-routeforge

Paste everything below the line into a fresh opencode session on branch `feat/route-bundles`.

You (the controller) drive Opus directly. Implementer work goes to the `kimi-implementer` subagent (Qwen). Spec compliance review goes to `kimi-spec-reviewer` (Opus). Code quality review goes to `kimi-code-reviewer` (Opus).

Only the prereq change is in scope. Do NOT start `route-forge` — it's a separate future session.

---

You are the controller for implementing OpenSpec change `schema-modernization-for-routeforge` on this repository's current branch (`feat/route-bundles`, off `fix/installer`). The branch will be squash-merged into main when done.

## Scope

Only `openspec/changes/schema-modernization-for-routeforge/`. Do NOT touch `openspec/changes/route-forge/` — that's a separate future change.

The capability spec files defining acceptance:

- `openspec/changes/schema-modernization-for-routeforge/specs/flight-time-storage/spec.md`
- `openspec/changes/schema-modernization-for-routeforge/specs/subfleet-capability/spec.md`
- `openspec/changes/schema-modernization-for-routeforge/specs/route-bundles/spec.md`
- `openspec/changes/schema-modernization-for-routeforge/specs/flight-visibility/spec.md`

Plus `proposal.md`, `design.md`, and `tasks.md` in the same directory.

## Your role

You orchestrate. You do not implement. For each task in `tasks.md`:

1. Read the task and surrounding context.
2. Dispatch `kimi-implementer` (Qwen) with the full task text + context.
3. Wait for its report (DONE / DONE_WITH_CONCERNS / BLOCKED / NEEDS_CONTEXT).
4. If DONE or DONE_WITH_CONCERNS, dispatch `kimi-spec-reviewer` (Opus) on the same task.
5. If spec-compliant, dispatch `kimi-code-reviewer` (Opus) on the same task.
6. If reviewers flag issues, hand them back to the implementer with specifics. Loop until clean.
7. Move to next task.

When all tasks are complete and all four quality gates pass cleanly, write a final completion report to `.routeforge-handoff/CONTROLLER-REPORT-prereq.md` and stop.

## Subagents available

- **`kimi-implementer`** — model: `opencode-go/qwen3.6-plus`. Implements ONE task. Reports DONE / DONE_WITH_CONCERNS / BLOCKED / NEEDS_CONTEXT.
- **`kimi-spec-reviewer`** — model: `anthropic/claude-opus-4-7`. Reviews whether the implementation matches the spec. Does NOT comment on code quality.
- **`kimi-code-reviewer`** — model: `anthropic/claude-opus-4-7`. Reviews code quality. Use ONLY after spec compliance passes.

Dispatch via the Task tool. Each invocation is fresh context — provide full task text and any context the subagent needs (file paths, spec excerpts, prior decisions).

## Implementer dispatch template

When dispatching `kimi-implementer`, provide a prompt with this shape:

```
## Task
<exact text from tasks.md for one task or one cohesive group of related tasks>

## Spec acceptance criteria
<paste the relevant Requirement(s) and Scenario(s) from the spec.md files
that this task must satisfy. Be specific — quote the actual SHALL statements
and WHEN/THEN scenarios.>

## Design context
<paste the relevant Decision(s) from design.md if the task touches them.
For example: if implementing the time-column accessor/mutator bridge,
paste Decision 1 verbatim.>

## Codebase pointers
<2-5 file paths the implementer should read to understand existing patterns.
Examples for this work:
  - database/migrations/2025_01_13_003704_create_phpvms_table.php (existing schema)
  - app/Models/Flight.php (existing model conventions)
  - app/Models/Observers/Sluggable.php (observer pattern example)
  - app/Filament/Resources/Flights/FlightResource.php (Filament v5 conventions)
>

## Filament v5 namespace cheat sheet
<paste this verbatim — Qwen needs the reminder every dispatch where Filament
is touched>

| Wrong | Correct |
|-------|---------|
| Filament\Forms\Components\Section | Filament\Schemas\Components\Section |
| Filament\Forms\Components\Grid | Filament\Schemas\Components\Grid |
| Filament\Forms\Components\Fieldset | Filament\Schemas\Components\Fieldset |
| Filament\Forms\Components\Tabs | Filament\Schemas\Components\Tabs |
| Filament\Forms\Components\Utilities\Get | Filament\Schemas\Components\Utilities\Get |
| Filament\Tables\Actions\Action | Filament\Actions\Action |
| Filament\Tables\Actions\DeleteAction | Filament\Actions\DeleteAction |

Form fields, infolist entries, table columns, table filters stay in their
v4 namespaces. When in doubt, check a recently-modified sibling Filament
file and mimic its imports exactly.

## Project conventions
- This is Laravel 12 + Filament 5 + PHP 8.3.
- Repository pattern (prettus) was just removed — do NOT extend BaseRepository
  or add new Repository classes. Use Model::query() directly.
- Soft-deleted models use the SoftDeletes trait and Spatie's LogsActivity.
- Modules (under modules/) are leaves; App code MUST NOT import from
  Modules\*.
- Run vendor/bin/phpstan analyse on every Filament file you create or modify,
  before moving to the next file. Namespace errors compound silently.
- Mass-assignment must route through mutators. If a spec scenario tests
  Flight::create([...]) routing through setDptTimeAttribute, do not bypass
  with raw DB inserts.

## Verification before reporting DONE
Run all four. Paste the actual output into your report:

  composer pint --test
  vendor/bin/phpstan analyse
  vendor/bin/rector --dry-run
  php artisan test --compact --filter=<relevant pattern>

## TDD requirement
Write Pest tests for every Scenario in the spec acceptance criteria FIRST.
Tests should fail. Then implement. Tests should pass. Then verify.

## Commit message
<conventional commits style, subject ≤50 chars. Example:
"feat(flights): add departure_time/arrival_time TIME columns">

## Report format
Use DONE / DONE_WITH_CONCERNS / BLOCKED / NEEDS_CONTEXT as your status.
Paste:
- Files changed (with brief one-line summary each)
- New tests added (file:test_name)
- Quality gate output (full, verbatim)
- Commit SHA(s)
- Anything you noticed that the reviewer should look at carefully
```

## Spec reviewer dispatch template

After implementer reports DONE:

```
## Task that was implemented
<paste exact task text given to the implementer>

## Spec acceptance criteria
<paste the same Requirements + Scenarios you gave the implementer>

## Implementer's report
<paste verbatim — do NOT summarize>

## Commit range to review
<base SHA>..<head SHA>

## Your job
Verify the implementation matches the spec. Read the actual code, not the
implementer's prose. For each Scenario, locate the test that proves it.
Output a Scenario → test mapping table.

Report ✅ Spec compliant or ❌ Issues found with file:line references.

Do NOT comment on code quality — that's the next reviewer.
```

## Code reviewer dispatch template

Only after spec review passes:

```
## Task that was implemented
<paste exact task text>

## Spec acceptance criteria (for context only — don't re-verify spec compliance)
<short summary>

## Commit range
<base SHA>..<head SHA>

## Focus areas for this codebase
- Eloquent: no N+1, no raw DB::, prefer Model::query().
- Filament v5 namespaces (see cheat sheet) — namespace errors that phpstan
  caught are not in scope; namespace errors phpstan missed are.
- Observers + LogsActivity: verify no event firing in withoutEvents blocks
  and no missed event firing where the spec requires logging.
- Module boundaries: App\* MUST NOT import from Modules\*.
- Form Requests for validation, not inline controller validation.
- Permission gating at the route or resource level for new admin features.

## Your job
Review code quality. Severity tiers: Critical / Important / Minor.

Output the standard format from your agent definition.
```

## Loop control

After each task:

1. If spec reviewer returns ❌ Issues found → dispatch implementer again with the issue list as the new task. Re-spec-review. Re-code-review.
2. If code reviewer returns Critical or Important issues → dispatch implementer with the fix list. Re-code-review (skip spec review unless the fix changed contract).
3. If both reviewers pass → move to next task in tasks.md.

Track your progress against `tasks.md` checkboxes. Do not check off a task until BOTH reviewers pass on it.

## DB setup note

The `.env` in this worktree points to `DB_HOST=mysql` (sail/docker network). When the implementer runs `php artisan test`, tests need either:

- Sail running: `./vendor/bin/sail up -d` (preferred — matches CI)
- Or SQLite override: temporarily set `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` in `.env` for test runs

The implementer's first dispatch should clarify which path it'll use. Either is fine.

## Hard constraints

- **Do NOT modify** `openspec/changes/schema-modernization-for-routeforge/proposal.md`, `design.md`, `tasks.md`, or any `specs/*/spec.md`. They are the contract.
- **Do NOT start** `route-forge` work — that's a separate future change.
- **Do NOT skip** the spec reviewer step, even if the implementer reports DONE with high confidence. Verification is the gate.
- **Do NOT skip** the code reviewer step, even if spec review passes cleanly. They're orthogonal concerns.
- **Do NOT silently merge** small fixes yourself between subagent dispatches. If something needs fixing, dispatch the implementer with the fix as a task. Keep audit trail clean.

## Initial steps

1. Read `openspec/changes/schema-modernization-for-routeforge/proposal.md` fully.
2. Read `openspec/changes/schema-modernization-for-routeforge/design.md` fully.
3. Read `openspec/changes/schema-modernization-for-routeforge/tasks.md` fully.
4. Read all four `specs/*/spec.md` files fully.
5. Confirm current branch is `feat/route-bundles` and tree is clean.
6. Plan the task dispatch order. The four phases (1.1 time columns, 1.2 subfleet capability, 1.3 route bundles, 1.4 visibility) have ordering constraints — Phase 1.4 depends on Phase 1.3 (bundles must exist before visibility cron can read bundle.enabled). Phases 1.1 and 1.2 are independent.
7. Begin Phase 1.1. Dispatch the first task to `kimi-implementer`.

## Final completion

When all tasks pass spec + code review and all four quality gates run clean on the whole branch (not just per-task), write `.routeforge-handoff/CONTROLLER-REPORT-prereq.md` with:

- All commits (SHA + subject)
- Final quality gate output (full)
- Scenario coverage table (every Scenario from every spec.md → which test proves it)
- Anything notable for the human reviewer before squash merge

Then stop and wait for human review.
