<?php

namespace App\Contracts;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Laracasts\Flash\Flash;

/**
 * Class Controller
 */
abstract class Controller extends \Illuminate\Routing\Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Write a error to the flash and redirect the user to a route
     *
     *
     * @param mixed $message
     * @param mixed $route
     *
     * @return mixed
     */
    public function flashError($message, $route)
    {
        Flash::error($message);

        return redirect(route($route))->withInput();
    }

    /**
     * Shortcut function to get the attributes from a request while running the validations
     *
     * @param Request $request
     * @param array   $attrs_or_validations
     * @param array   $addtl_fields
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     *
     * @return array
     */
    public function getFromReq($request, $attrs_or_validations, $addtl_fields = null)
    {
        // See if a list of values is passed in, or if a validation list is passed in
        $is_validation = false;
        if (\count(array_filter(array_keys($attrs_or_validations), '\is_string')) > 0) {
            $is_validation = true;
        }

        if ($is_validation) {
            $this->validate($request, $attrs_or_validations);
        }

        $fields = [];
        foreach ($attrs_or_validations as $idx => $field) {
            if ($is_validation) {
                $field = $idx;
            }

            if ($request instanceof Request) {
                if ($request->filled($field)) {
                    $fields[$field] = $request->input($field);
                }
            } else {
                /* @noinspection NestedPositiveIfStatementsInspection */
                if (array_key_exists($field, $request)) {
                    $fields[$field] = $request[$field];
                }
            }
        }

        if (!empty($addtl_fields) && \is_array($addtl_fields)) {
            $fields = array_merge($fields, $addtl_fields);
        }

        return $fields;
    }

    /**
     * Simple normalized method for forming the JSON responses
     *
     * @param null|mixed $count
     * @param mixed      $attrs
     * @param mixed      $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function message($message, $count = null, $attrs = [])
    {
        $ret = [
            'message' => $message,
            'attrs'   => $attrs,
        ];

        if ($count !== null) {
            $ret['count'] = $count;
        }

        return response()->json($ret);
    }
}
