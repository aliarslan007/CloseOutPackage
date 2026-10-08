<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Actions;

use App\Domains\ChangeOrders\DTOs\ChangeOrderDTO;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateChangeOrderAction
{
    public function __construct(
        private readonly CreateChangeOrderAction $createAction,
        private readonly AttachChangeOrderPhotosAction $attachPhotosAction,
        private readonly DetachChangeOrderPhotoAction $detachPhotoAction,
    ) {}

    /**
     * Draft-only update — policy guarantees status is DRAFT.
     *
     * @param  list<int>  $recipientIds
     */
    public function execute(ChangeOrder $changeOrder, ChangeOrderDTO $dto, array $recipientIds = []): ChangeOrder
    {
        return DB::transaction(function () use ($changeOrder, $dto, $recipientIds): ChangeOrder {
            $changeOrder->update([
                'title' => $dto->title,
                'description' => $dto->description,
                'change_order_date' => $dto->changeOrderDate,
                'total_amount' => $dto->includedTotal(),
            ]);

            // ponytail: reuse Create's item+recipient sync — same shape, only the parent row differs
            $this->createAction->syncItems($changeOrder, $dto->items);
            $this->createAction->syncRecipients($changeOrder, $recipientIds);

            if ($dto->photoIds !== null) {
                $photosToDetach = $changeOrder->photos()
                    ->when(! empty($dto->photoIds), fn ($query) => $query->whereNotIn('id', $dto->photoIds))
                    ->get();

                foreach ($photosToDetach as $photo) {
                    $this->detachPhotoAction->execute($photo);
                }
            }

            if ($dto->mediaAssetIds !== []) {
                /** @var User $actor */
                $actor = auth()->user() ?? $changeOrder->creator;
                $this->attachPhotosAction->execute($changeOrder, $dto->mediaAssetIds, $actor);
            }

            return $changeOrder->refresh();
        });
    }
}
