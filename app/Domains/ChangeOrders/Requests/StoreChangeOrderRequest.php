<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Requests;

use App\Domains\ChangeOrders\DTOs\ChangeOrderDTO;
use App\Domains\ChangeOrders\DTOs\ChangeOrderItemDTO;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreChangeOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return Gate::allows('create', [ChangeOrder::class, $project]);
    }

    /**
     * Accept a legacy plain-string vendor_name (old payloads/tests) by
     * wrapping it into a one-element list before validation runs.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $row) {
            if (! is_array($row) || ! array_key_exists('vendor_name', $row)) {
                continue;
            }

            if (is_string($row['vendor_name'])) {
                $items[$index]['vendor_name'] = trim($row['vendor_name']) === '' ? [] : [$row['vendor_name']];
            }
        }

        $this->merge(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.ChangeOrder::MAX_TITLE_LENGTH],
            'description' => ['nullable', 'string', 'max:'.ChangeOrder::MAX_DESCRIPTION_LENGTH],
            'change_order_date' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:+2 years'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.description' => ['required', 'string', 'max:'.ChangeOrder::MAX_ITEM_DESCRIPTION_LENGTH],
            'items.*.vendor_name' => ['nullable', 'array', 'max:'.ChangeOrder::MAX_ITEM_VENDORS],
            'items.*.vendor_name.*' => ['required', 'string', 'max:100'],
            'items.*.category' => ['nullable', 'string', 'max:100'],
            'items.*.amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'items.*.is_included' => ['nullable', 'boolean'],
            'items.*.cost_absorbed_by_solucomp' => ['nullable', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => [
                'integer',
                Rule::exists('customer_project_access', 'user_id')->where(
                    fn ($query) => $query->where('project_id', $this->route('project')->id),
                ),
            ],
            'media_asset_ids' => ['nullable', 'array', 'max:'.ChangeOrder::MAX_PHOTOS, 'distinct'],
            'media_asset_ids.*' => ['integer', 'exists:media_assets,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'change_order_date.date' => 'The change order date must be a valid date.',
            'change_order_date.after_or_equal' => 'The change order date cannot be earlier than 2000-01-01.',
            'change_order_date.before_or_equal' => 'The change order date cannot be scheduled more than 2 years into the future.',
        ];
    }

    public function toDTO(): ChangeOrderDTO
    {
        /** @var list<array<string, mixed>> $rawItems */
        $rawItems = $this->validated('items');

        $items = array_values(array_map(
            fn (array $row): ChangeOrderItemDTO => new ChangeOrderItemDTO(
                id: isset($row['id']) && is_int($row['id']) ? $row['id'] : null,
                sortOrder: is_int($row['sort_order'] ?? null) ? $row['sort_order'] : 0,
                description: is_string($row['description']) ? $row['description'] : '',
                vendorName: $this->vendorNames($row),
                category: isset($row['category']) && is_string($row['category']) ? $row['category'] : null,
                amount: is_numeric($row['amount']) ? (float) $row['amount'] : 0.0,
                isIncluded: (bool) ($row['is_included'] ?? true),
                costAbsorbedBySolucomp: (bool) ($row['cost_absorbed_by_solucomp'] ?? false),
                customerDecision: null,
                customerDecisionNote: null,
            ),
            $rawItems,
        ));

        /** @var list<int> $recipientIds */
        $recipientIds = array_map(intval(...), (array) $this->validated('recipient_ids', []));

        /** @var list<int> $mediaAssetIds */
        $mediaAssetIds = array_map(intval(...), (array) $this->validated('media_asset_ids', []));

        return new ChangeOrderDTO(
            title: is_string($this->validated('title')) ? $this->validated('title') : '',
            description: $this->nullableString('description'),
            items: $items,
            changeOrderDate: $this->nullableString('change_order_date'),
            recipientIds: $recipientIds,
            mediaAssetIds: $mediaAssetIds,
        );
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }

    /**
     * Validation already guarantees a string list; only normalize empty to null.
     * The model setter handles trim/filter on write.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>|null
     */
    private function vendorNames(array $row): ?array
    {
        $vendors = $row['vendor_name'] ?? null;

        if (! is_array($vendors)) {
            return null;
        }

        $names = array_values(array_filter($vendors, is_string(...)));

        return $names === [] ? null : $names;
    }
}
