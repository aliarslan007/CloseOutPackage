<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Controllers;

use App\Domains\ChangeOrders\Actions\AttachChangeOrderPhotosAction;
use App\Domains\ChangeOrders\Actions\DetachChangeOrderPhotoAction;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\ChangeOrders\Requests\AttachChangeOrderPhotosRequest;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Change-order photos: attach (staff, draft only), remove (staff, draft only)
 * and one stream endpoint that serves staff, portal customers and no-auth
 * email-token viewers through a single authorization funnel.
 */
final class ChangeOrderPhotoController extends Controller
{
    public function __construct(
        private readonly AttachChangeOrderPhotosAction $attachAction,
        private readonly DetachChangeOrderPhotoAction $detachAction,
    ) {}

    public function store(AttachChangeOrderPhotosRequest $request, Project $project, ChangeOrder $changeOrder): JsonResponse
    {
        $this->authorize('update', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        $photos = $this->attachAction->execute($changeOrder, $request->integerList('media_asset_ids'), $this->user());

        if ($photos === []) {
            throw ValidationException::withMessages([
                'media_asset_ids' => 'None of the selected photos could be added to this change order.',
            ]);
        }

        return response()->json([
            'data' => $photos,
            'count' => count($photos),
        ], 201);
    }

    public function destroy(Project $project, ChangeOrder $changeOrder, ChangeOrderPhoto $photo): Response
    {
        $this->authorize('update', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);
        abort_unless($photo->change_order_id === $changeOrder->getKey(), 404);

        $this->detachAction->execute($photo);

        return response()->noContent();
    }

    public function stream(Request $request, ChangeOrderPhoto $photo): BinaryFileResponse
    {
        $this->authorizeStream($request, $photo);

        $path = Storage::disk('private')->path($photo->path);
        abort_unless(is_file($path), 404);

        $filename = Str::ascii($photo->original_name) ?: 'change-order-photo';

        return response()->file($path, [
            'Content-Type' => $photo->mime_type,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * One funnel, checked in order: a valid, unexpired recipient token grants
     * view-only access; otherwise the logged-in user must be staff (via the
     * change order policy) or a recipient of the change order.
     */
    private function authorizeStream(Request $request, ChangeOrderPhoto $photo): void
    {
        $token = $request->query('token');

        if (is_string($token) && $token !== '' && $this->tokenGrantsAccess($photo, $token)) {
            return;
        }

        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $changeOrder = $photo->changeOrder;

        if ($user->can('view', $changeOrder)) {
            return;
        }

        abort(403);
    }

    private function tokenGrantsAccess(ChangeOrderPhoto $photo, string $token): bool
    {
        return $photo->changeOrder
            ->recipients()
            ->where('token_hash', ChangeOrderRecipient::hashToken($token))
            ->where(function ($query): void {
                // View access survives a completed decision; it must not survive
                // an expired or revoked token (mirrors hasExpired()).
                $query->whereNull('token_expires_at')->orWhere('token_expires_at', '>', now());
            })
            ->exists();
    }
}
