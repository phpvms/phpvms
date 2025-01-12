<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Models\File;
use App\Services\FileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laracasts\Flash\Flash;

class FileController extends Controller
{
    public function __construct(
        private readonly FileService $fileSvc
    ) {
    }

    /**
     * Store a newly file.
     *
     *
     * @throws \Hashids\HashidsException
     */
    public function store(Request $request): RedirectResponse
    {
        $attrs = $request->post();

        /*
         * Not using a form validation here because when it redirects, it leaves
         * the parent forms all blank, even though it goes back to the right place.
         *
         * The fields are also named file_name and file_description so that if there
         * are validation errors, the flash messages  doesn't conflict with other
         * fields on the page that might have the "name" and "description" fields
         *
         * Was also going to use the "required_without" rule, but that doesn't appear
         * to work properly with a file upload
         */
        $validator = Validator::make(
            $request->all(),
            [
                'file_name'        => 'required',
                'file_description' => 'nullable',
                'file'             => 'nullable|file',
                'url'              => 'nullable|url',
                /*'file'             => [
                    Rule::requiredIf(function () {
                        return request()->filled('url') === false;
                    }),
                    'file',
                ],
                'url' => [
                    Rule::requiredIf(function () {
                        return request()->hasFile('file') === false;
                    }),
                    'url',
                ],*/
            ],
            [
                'file.required' => 'File or URL are required',
                'url.required'  => 'File or URL are required',
            ]
        );

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput($request->all());
        }

        if (!$request->hasFile('file') && !$request->filled('url')) {
            $validator->errors()->add('url', 'A URL or file must be uploaded!');

            return redirect()->back()->withErrors($validator)->withInput($request->all());
        }

        Log::info('Uploading files', $attrs);

        $attrs['name'] = $attrs['file_name'];
        $attrs['description'] = $attrs['file_description'];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $this->fileSvc->saveFile($file, 'files', $attrs);
        }

        // Didn't provide a file to upload, just a URL to a file
        // Create the model directly and just associate that
        elseif ($request->filled('url')) {
            $file = new File($attrs);
            $file->path = $attrs['url'];
            $file->save();
        }

        Flash::success('Files saved successfully');

        return redirect()->back();
    }

    /**
     * Remove the file from storage.
     *
     *
     * @throws \Exception
     */
    public function destroy(string $id): RedirectResponse
    {
        $file = File::find($id);
        if (!$file) {
            Flash::error('File doesn\'t exist');

            return redirect()->back();
        }

        $this->fileSvc->removeFile($file);

        Flash::success('File deleted successfully.');

        return redirect()->back();
    }
}
