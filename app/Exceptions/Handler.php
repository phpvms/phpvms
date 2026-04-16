<?php

namespace App\Exceptions;

use App\Exceptions\Converters\GenericExceptionAbstract;
use App\Exceptions\Converters\SymfonyException;
use App\Exceptions\Converters\ValidationException;
use App\Http\Middleware\SetActiveTheme;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Class Handler
 */
class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     */
    protected $dontReport = [
        // AuthenticationException::class,
        // AuthorizationException::class,
        AbstractHttpException::class,
        IlluminateValidationException::class,
        ModelNotFoundException::class,
        SymfonyHttpException::class,
        TokenMismatchException::class,
    ];

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request $request
     * @return mixed
     */
    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {
            return $this->handleApiError($request, $exception);
        }

        (new SetActiveTheme())->setTheme($request);
        if ($exception instanceof AbstractHttpException && $exception->getStatusCode() === 403) {
            return redirect()->guest('login');
        }

        return parent::render($request, $exception);
    }

    /**
     * Handle errors in the API
     *
     *
     * @return JsonResponse|Response
     */
    private function handleApiError($request, Throwable $exception)
    {
        Log::error('API Error: '.$exception->getMessage(), $exception->getTrace());

        if ($exception instanceof AbstractHttpException) {
            return $exception->getResponse();
        }

        /*
         * Not of the HttpException abstract class. Map these into
         */

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            $error = new AssetNotFound($exception);

            return $error->getResponse();
        }

        // Custom exceptions should be extending HttpException
        if ($exception instanceof SymfonyHttpException) {
            $error = new SymfonyException($exception);

            return $error->getResponse();
        }

        // Create the detailed errors from the validation errors
        if ($exception instanceof IlluminateValidationException) {
            $error = new ValidationException($exception);

            return $error->getResponse();
        }

        $error = new GenericExceptionAbstract($exception);

        return $error->getResponse();
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  Request                                $request
     * @return Response|JsonResponse|RedirectResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            $error = new Unauthenticated();

            return $error->getResponse();
        }

        return redirect()->guest('login');
    }
}
