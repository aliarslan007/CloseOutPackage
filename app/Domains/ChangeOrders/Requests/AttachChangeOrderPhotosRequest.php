<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Requests;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Rules\WithinChangeOrderPhotoLimit;
use Illuminate\Foundation\Http\FormRequest;

final class AttachChangeOrderPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization lives in the controller via ChangeOrderPolicy::update.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ChangeOrder $changeOrder */
        $changeOrder = $this->route('change_order');

        return [
            'media_asset_ids' => [
                'required',
                'array',
                'min:1',
                'max:'.ChangeOrder::MAX_PHOTOS,
                'distinct',
                new WithinChangeOrderPhotoLimit($changeOrder),
            ],
            'media_asset_ids.*' => ['integer', 'exists:media_assets,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function integerList(string $key): array
    {
        $items = $this->validated($key);

        return is_array($items) ? array_map('intval', $items) : [];
    }
}
