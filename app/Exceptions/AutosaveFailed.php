<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Filament\Concerns\AutosavesFields;
use Exception;

/**
 * A {@see AutosavesFields::persistAutosavedField()} implementation could not
 * write the field.
 *
 * The message is shown to the admin verbatim, so pass a translated string.
 * `runAutosave()` catches this, sends one danger toast, and skips both the
 * success toast and the saved-tick event — which is what a hand-rolled
 * `Notification` + `return` could not do, since the trait carried on and
 * reported success on top of the failure.
 */
class AutosaveFailed extends Exception {}
