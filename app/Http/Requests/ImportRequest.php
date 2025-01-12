<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use Illuminate\Http\Request;

/**
 * Validate that the files are imported.
 */
class ImportRequest extends FormRequest
{
    public static $rules = [
        'csv_file' => 'required|file',
    ];

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(Request $request)
    {
        \Validator::make($request->all(), static::$rules)->validate();
    }

    public function rules(): array
    {
        return static::$rules;
    }
}
