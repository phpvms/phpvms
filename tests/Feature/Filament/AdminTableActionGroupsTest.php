<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('groups edit and delete table actions into a menu', function (): void {
    $offenders = [];

    foreach (File::allFiles(app_path('Filament')) as $file) {
        $contents = $file->getContents();
        if (!str_contains($contents, '->recordActions([')) {
            continue;
        }
        if (!str_contains($contents, 'EditAction::make')) {
            continue;
        }
        if (!str_contains($contents, 'DeleteAction::make')) {
            continue;
        }

        if (!preg_match('/->recordActions\(\[\s*ActionGroup::make\(/s', $contents)) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
})->group('filament');
