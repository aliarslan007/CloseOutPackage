<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Requests;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReorderChangeOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return Gate::allows('reorder', [ChangeOrder::class, $project]);
    }

    /**
     * Change orders are project-scoped in the UI, so the payload may only
     * carry ids from the project in the route. Soft-deleted rows are
     * excluded so a trashed draft cannot be pulled back into the ordering.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('change_orders', 'id')
                    ->where('project_id', $project->id)
                    ->whereNull('deleted_at'),
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
