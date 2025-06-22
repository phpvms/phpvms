<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService extends Service
{
    /**
     * Save a file to disk and return a File asset
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string                        $folder
     *
     * @throws \Hashids\HashidsException
     */
    public function saveFile($file, $folder, array $attrs): \App\Models\File
    {
        $attrs = array_merge([
            'name'         => '',
            'description'  => '',
            'public'       => false,
            'ref_model'    => '',
            'ref_model_id' => '',
            'disk'         => config('filesystems.public_files'),
        ], $attrs);

        $id = File::createNewHashId();
        $path_info = pathinfo($file->getClientOriginalName());

        // Create the file, add the ID to the front of the file to account
        // for any duplicate filenames, but still can be found in an `ls`
        $filename = $id.'_'.\Illuminate\Support\Str::slug(trim($path_info['filename'])).'.'.$path_info['extension'];
        $file_path = $file->storeAs($folder, $filename, $attrs['disk']);

        Log::info('File saved to '.$file_path);

        $asset = new File($attrs);
        $asset->id = $id;
        $asset->path = $file_path;
        $asset->save();

        return $asset;
    }

    /**
     * Remove a file, if it exists on disk
     *
     * @param File $file
     *
     * @throws \Exception
     */
    public function removeFile($file): void
    {
        if (!Str::startsWith($file->path, 'http')) {
            Storage::disk(config('filesystems.public_files'))
                ->delete($file->path);
        }

        $file->delete();
    }
}
