<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Abstract class for an exception which needs to satisty the RFC 78707 interface
 */
abstract class AbstractHttpException extends SymfonyHttpException implements HttpExceptionInterface
{
    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    abstract public function getErrorType(): string;

    /**
     * Get the detailed error string
     */
    abstract public function getErrorDetails(): string;

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    abstract public function getErrorMetadata(): array;

    /**
     * Return the error message as JSON
     */
    public function getJson()
    {
        $response = [];

        $response['type'] = config('phpvms.error_root').'/'.$this->getErrorType();
        $response['title'] = $this->getMessage();
        $response['details'] = $this->getErrorDetails();
        $response['status'] = $this->getStatusCode();

        // For backwards compatibility
        $response['error'] = [
            'status'  => $this->getStatusCode(),
            'message' => $this->getErrorDetails(),
        ];

        return array_merge($response, $this->getErrorMetadata());
    }

    /**
     * Return a response object that can be used by Laravel
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function getResponse()
    {
        $headers = [];
        $headers['content-type'] = 'application/problem+json';
        $headers = array_merge($headers, $this->getHeaders());

        return response()->json($this->getJson(), $this->getStatusCode(), $headers);
    }
}
