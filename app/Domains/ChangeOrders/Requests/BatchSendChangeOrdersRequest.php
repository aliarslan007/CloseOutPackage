<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Requests;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class BatchSendChangeOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'change_order_ids' => ['required', 'array', 'min:1'],
            'change_order_ids.*' => ['required', 'integer', 'exists:change_orders,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function changeOrderIds(): array
    {
        /** @var list<int> $ids */
        $ids = $this->validated('change_order_ids');

        return array_values(array_map('intval', $ids));
    }
}
