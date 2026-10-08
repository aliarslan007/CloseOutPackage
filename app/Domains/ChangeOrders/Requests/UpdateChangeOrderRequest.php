<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Requests;

use App\Domains\ChangeOrders\DTOs\ChangeOrderDTO;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Rules\WithinChangeOrderPhotoLimit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateChangeOrderRequest extends StoreChangeOrderRequest
{
    public function authorize(): bool
    {
        /** @var ChangeOrder $changeOrder */
        $changeOrder = $this->route('change_order');

        return Gate::allows('update', $changeOrder);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var ChangeOrder $changeOrder */
        $changeOrder = $this->route('change_order');

        // photo_ids are the photos kept from the current set; media_asset_ids
        // are new ones. Both must fit inside the same cumulative cap.
        $rawPhotoIds = $this->input('photo_ids');
        $keptPhotoIds = $rawPhotoIds === null
            ? null
            : array_values(array_map(intval(...), (array) $rawPhotoIds));

        $rules['photo_ids'] = ['nullable', 'array', 'max:'.ChangeOrder::MAX_PHOTOS];
        $rules['photo_ids.*'] = [
            'integer',
            Rule::exists('change_order_photos', 'id')->where(
                fn ($query) => $query->where('change_order_id', $changeOrder->id)
            ),
        ];

        $rules['media_asset_ids'] = [
            'nullable',
            'array',
            'max:'.ChangeOrder::MAX_PHOTOS,
            'distinct',
            new WithinChangeOrderPhotoLimit($changeOrder, $keptPhotoIds),
        ];

        return $rules;
    }

    public function toDTO(): ChangeOrderDTO
    {
        $dto = parent::toDTO();

        $photoIds = $this->has('photo_ids')
            ? array_map(intval(...), (array) $this->validated('photo_ids', []))
            : null;

        return new ChangeOrderDTO(
            title: $dto->title,
            description: $dto->description,
            items: $dto->items,
            changeOrderDate: $dto->changeOrderDate,
            recipientIds: $dto->recipientIds,
            mediaAssetIds: $dto->mediaAssetIds,
            photoIds: $photoIds,
        );
    }
}
