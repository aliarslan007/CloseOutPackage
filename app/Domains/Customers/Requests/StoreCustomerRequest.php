<?php

declare(strict_types=1);

namespace App\Domains\Customers\Requests;

use App\Domains\Customers\DTOs\CreateCustomerDTO;
use App\Domains\Customers\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('customers', 'name')->withoutTrashed()],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('customers', 'code')->withoutTrashed()],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toDTO(): CreateCustomerDTO
    {
        /** @var string $name */
        $name = $this->validated('name');
        /** @var string $code */
        $code = $this->validated('code');
        $description = $this->validated('description');
        $isActive = $this->validated('is_active');

        return new CreateCustomerDTO(
            name: $name,
            code: $code,
            description: is_string($description) ? $description : null,
            isActive: is_bool($isActive) ? $isActive : true,
        );
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCode();
    }

    private function normalizeCode(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => strtoupper(trim($code))]);
        }
    }
}
