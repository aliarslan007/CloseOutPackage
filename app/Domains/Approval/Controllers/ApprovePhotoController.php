<?php

declare(strict_types=1);

namespace App\Domains\Approval\Controllers;

use App\Domains\Approval\Actions\ReviewPhotoAction;
use App\Domains\Approval\DTOs\ReviewPhotoDTO;
use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Approval\Requests\ApprovePhotoRequest;
use App\Domains\Photos\Models\Photo;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ApprovePhotoController extends Controller
{
    public function __construct(private readonly ReviewPhotoAction $reviewPhotoAction) {}

    public function __invoke(ApprovePhotoRequest $request, Photo $photo): RedirectResponse
    {
        $this->reviewPhotoAction->execute(
            new ReviewPhotoDTO(
                photoId: $photo->id,
                decision: ApprovalDecision::APPROVED,
            ),
            $this->user(),
        );

        return redirect()->back()->with('success', 'Photo approved.');
    }
}
