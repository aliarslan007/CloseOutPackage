<?php

declare(strict_types=1);

namespace App\Domains\Photos\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Actions\ReplacePhotoAction;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Requests\ReplacePhotoRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ReplacePhotoController extends Controller
{
    public function __construct(private readonly ReplacePhotoAction $replacePhotoAction) {}

    public function __invoke(ReplacePhotoRequest $request, CloseoutPackage $package, Photo $photo): RedirectResponse
    {
        abort_unless($photo->closeout_package_id === $package->id, 404);

        $file = $request->file('photo');

        if ($file === null) {
            return redirect()->back()->with('error', 'No photo file provided.');
        }

        try {
            $this->replacePhotoAction->execute($file, $request->toDTO(), $this->user());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Photo replaced.');
    }
}
