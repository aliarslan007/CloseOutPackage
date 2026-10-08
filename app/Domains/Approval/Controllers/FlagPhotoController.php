<?php

declare(strict_types=1);

namespace App\Domains\Approval\Controllers;

use App\Domains\Approval\Actions\FlagPhotoAction;
use App\Domains\Approval\Requests\FlagPhotoRequest;
use App\Domains\Photos\Models\Photo;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class FlagPhotoController extends Controller
{
    public function __construct(private readonly FlagPhotoAction $flagPhotoAction) {}

    public function __invoke(FlagPhotoRequest $request, Photo $photo): RedirectResponse
    {
        $this->flagPhotoAction->execute($photo, $request->reason(), $this->user());

        return redirect()->back()->with('success', 'Photo flagged.');
    }
}
