<?php

declare(strict_types=1);

namespace App\Support\Http\Requests;

use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            ApiResponse::error(
                message: 'The submitted data is invalid.',
                code: 'VALIDATION_ERROR',
                status: 422,
                errors: $validator->errors()->toArray(),
            ),
        );
    }
}
