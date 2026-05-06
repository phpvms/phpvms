<?php

declare(strict_types=1);

namespace App\Exceptions\Converters;

use App\Exceptions\AbstractHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SymfonyException extends AbstractHttpException
{
    public function __construct(private readonly HttpException $exception)
    {
        parent::__construct(
            $this->exception->getStatusCode(),
            $this->exception->getMessage(),
            null,
            $this->exception->getHeaders()
        );
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'http-exception';
    }

    /**
     * Get the detailed error string
     */
    public function getErrorDetails(): string
    {
        return $this->getMessage();
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    public function getErrorMetadata(): array
    {
        // Only add trace if in dev
        if (config('app.env') === 'dev') {
            return [
                'trace' => $this->exception->getTrace()[0],
            ];
        }

        return [];
    }
}
