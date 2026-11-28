<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Data migration: avatars were written to public/uploads/avatars, but their URL is
 * built on the public_files disk, so they returned 404. Copy each one onto that disk
 * under the same `avatars/<name>` path the users table already stores, then delete
 * the old file.
 *
 * A file that is already on the disk is left alone. Safe to run again. A failed copy
 * fails the migration, so it is not recorded and runs again on the next upgrade.
 */
return new class() extends Migration
{
    public function up(): void
    {
        $legacy = public_path('uploads/avatars');
        if (!is_dir($legacy)) {
            return;
        }

        $disk = Storage::disk(config('filesystems.public_files'));
        $failed = [];

        foreach (File::files($legacy) as $file) {
            $path = 'avatars/'.$file->getFilename();

            if (!$disk->exists($path)) {
                $stream = fopen($file->getPathname(), 'rb');
                $written = $disk->writeStream($path, $stream, ['visibility' => 'public']);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (!$written) {
                    $failed[] = $file->getFilename();

                    continue;
                }
            }

            File::delete($file->getPathname());
        }

        if ($failed !== []) {
            throw new RuntimeException('Could not move avatars to the public files disk: '.implode(', ', $failed));
        }
    }
};
