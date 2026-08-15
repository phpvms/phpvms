<?php

declare(strict_types=1);

use App\Filament\Resources\Awards\Pages\CreateAward;
use App\Models\Award;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config('filesystems.public_files'));
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

/**
 * image_file routes through ImageUploadService like every other admin image
 * upload -- a PNG drop must be saved as WebP under awards/, and
 * Award::image()'s accessor (Award.php:196-211) must still resolve it to a
 * URL, since that accessor only builds one for a path starting with
 * `awards/`.
 */
it('converts an uploaded award image to webp and resolves it back to a URL', function (): void {
    Livewire::test(CreateAward::class)
        ->fillForm([
            'name'       => 'Webp Award',
            'image_file' => UploadedFile::fake()->image('badge.png', 64, 64),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $award = Award::where('name', 'Webp Award')->firstOrFail();

    expect($award->image_url)->toStartWith('awards/')
        ->and($award->image_url)->toEndWith('.webp');

    Storage::disk(config('filesystems.public_files'))->assertExists($award->image_url);

    expect($award->image)->toBe(Storage::disk(config('filesystems.public_files'))->url($award->image_url));
});
