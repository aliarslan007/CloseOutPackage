<?php

declare(strict_types=1);

namespace App\Domains\Photos\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Actions\UpdatePhotoNotesAction;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Requests\UpdatePhotoNotesRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class UpdatePhotoNotesController extends Controller
{
    public function __construct(
        private readonly UpdatePhotoNotesAction $updatePhotoNotesAction,
    ) {}

    public function __invoke(
        UpdatePhotoNotesRequest $request,
        CloseoutPackage $package,
        Photo $photo,
    ): RedirectResponse {
        abort_unless($photo->closeout_package_id === $package->id, 404);

        $dto = $request->toDTO();
        $this->updatePhotoNotesAction->execute($photo, $dto, $this->user());

        $message = $dto->notes === null ? 'Photo note removed.' : 'Photo note saved.';

        return redirect()->back()->with('success', $message);
    }
}
