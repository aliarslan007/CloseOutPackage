<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Requests;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderGlobalChangeOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reorderGlobally', ChangeOrder::class);
    }

    /**
     * The all-projects list spans projects, so any change order the user can
     * see may appear here; the payload is not project scoped. Soft-deleted
     * rows are excluded so a trashed draft cannot be pulled back into the
     * ordering.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('change_orders', 'id')->whereNull('deleted_at'),
            ],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<array{id: int, sort_order: int}>
     */
    public function items(): array
    {
        /** @var list<array{id: int, sort_order: int}> $items */
        $items = $this->validated('items');

        return array_values(array_map(
            fn (array $item): array => ['id' => (int) $item['id'], 'sort_order' => (int) $item['sort_order']],
            $items,
        ));
    }
}
