<?php

namespace App\Exceptions\Converters;

use App\Exceptions\AbstractHttpException;

class ValidationException extends AbstractHttpException
{
    private string $errorDetail;

    private $errors;

    public function __construct(private readonly \Illuminate\Validation\ValidationException $validationException)
    {
        $this->processValidationErrors();

        parent::__construct(
            400,
            'Validation Error'
        );
    }

    private function processValidationErrors(): void
    {
        $error_messages = [];
        $this->errors = $this->validationException->errors();
        foreach ($this->errors as $error) {
            $error_messages[] = implode(', ', $error);
        }

        $this->errorDetail = implode(', ', $error_messages);
        // Log::error('Validation errors', $this->errors);
    }

    /**
     * Return the RFC 7807 error type (without the URL root)
     */
    public function getErrorType(): string
    {
        return 'validation-exception';
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    public function getErrorDetails(): string
    {
        return $this->errorDetail;
    }

    /**
     * Return an array with the error details, merged with the RFC7807 response
     */
    public function getErrorMetadata(): array
    {
        return [
            'errors' => $this->errors,
        ];
    }
}
