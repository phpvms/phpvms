<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function moveAvatarsMigration(): object
{
    return require base_path('database/migrations_data/2026_11_28_000000_move_avatars_to_public_disk.php');
}

// The migration deletes what it moves, so point public_path() at a scratch directory
// and leave the checkout's public/uploads alone.
beforeEach(function (): void {
    $this->publicPath = public_path();
    $this->scratch = storage_path('framework/testing/move-avatars-'.uniqid());
    app()->usePublicPath($this->scratch);

    $this->disk = Storage::fake(config('filesystems.public_files'));
    $this->legacy = public_path('uploads/avatars/VMS0001.png');
    File::ensureDirectoryExists(dirname($this->legacy));
    File::put($this->legacy, 'legacy');
});

afterEach(function (): void {
    app()->usePublicPath($this->publicPath);
    File::deleteDirectory($this->scratch);
});

it('moves a legacy avatar onto the public files disk under the same path', function (): void {
    moveAvatarsMigration()->up();

    $path = 'avatars/'.basename($this->legacy);
    expect($this->disk->get($path))->toBe('legacy')
        ->and(File::exists($this->legacy))->toBeFalse();
});

it('keeps an avatar already on the disk and is safe to run again', function (): void {
    $path = 'avatars/'.basename($this->legacy);
    $this->disk->put($path, 'current');

    moveAvatarsMigration()->up();
    moveAvatarsMigration()->up();

    expect($this->disk->get($path))->toBe('current')
        ->and(File::exists($this->legacy))->toBeFalse();
});
