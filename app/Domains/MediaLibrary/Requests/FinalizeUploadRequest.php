<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The finalize endpoint has no input fields to validate — its only credential
 * is the upload token in the Authorization header, verified inside
 * FinalizeUploadAction. This class exists for type safety and middleware
 * consistency with the other upload endpoints.
 */
class FinalizeUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership via the policy; the upload token is verified in the action.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
