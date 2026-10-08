<?php

declare(strict_types=1);

namespace App\Domains\Approval\Controllers;

use App\Domains\Approval\Actions\RequestPhotoReplacementAction;
use App\Domains\Approval\Requests\RequestPhotoReplacementRequest;
use App\Domains\Photos\Models\Photo;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class RequestPhotoReplacementController extends Controller
{
    public function __construct(private readonly RequestPhotoReplacementAction $requestReplacementAction) {}

    public function __invoke(RequestPhotoReplacementRequest $request, Photo $photo): RedirectResponse
    {
        $this->requestReplacementAction->execute($photo, $request->reason(), $this->user());

        return redirect()->back()->with('success', 'Replacement requested.');
    }
}
