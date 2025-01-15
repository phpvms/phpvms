<?php

namespace Modules\Sample\Http\Controllers\Api;

use App\Contracts\Controller;
use Illuminate\Http\Request;

/**
 * Class SampleController
 */
class SampleController extends Controller
{
    /**
     * Just send out a message
     *
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return $this->message('Hello, world!');
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function hello(Request $request)
    {
        // Another way to return JSON, this for a custom response
        // It's recommended to use Resources for responses from the database
        return response()->json([
            'name' => Auth::user()->name,
        ]);
    }
}
