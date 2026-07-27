<?php

declare(strict_types=1);

/**
 * `App\Enums\PirepStatus` is deprecated. Use {@see \App\Enums\PirepPhase}.
 *
 * The enum describes what the aircraft is doing, which is a phase and not a
 * status — `PirepResource` has published the column as `phase` all along, and
 * `pireps.status` is the only place the old word survives. The class is the
 * last one that still said "status", so it was renamed and this alias left
 * behind for code written against the old name.
 *
 * This is a `class_alias`, not a second enum. `PirepStatus::TAXI` and
 * `PirepPhase::TAXI` are the same case: identity comparison holds, `instanceof`
 * holds, model casts declared against either name behave identically, and the
 * persisted three-character values are untouched.
 *
 * PHP enums are implicitly final, so `enum PirepPhase extends PirepStatus` is a
 * parse error and an interface would not deprecate anything — an alias is the
 * only construct that gives the two names one identity. The cost is that static
 * analysis and IDEs will not follow it, which is preferable to two real enums
 * whose cases compare unequal and fail silently at runtime.
 *
 * There is no class declaration in this file on purpose. Composer resolves
 * `App\Enums\PirepStatus` to this path through PSR-4, includes it, and the
 * alias below is what defines the name. `composer.json` pins
 * `classmap-authoritative` to `false` and `App\Addons\Support\AutoloadGuard`
 * throws at boot if it is ever turned on, so the PSR-4 fallback this depends on
 * is a property the application already guarantees for addons.
 *
 * @deprecated Use \App\Enums\PirepPhase instead.
 */

namespace App\Enums;

class_alias(PirepPhase::class, PirepStatus::class);
