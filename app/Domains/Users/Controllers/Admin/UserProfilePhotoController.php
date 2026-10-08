<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers\Admin;

use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserProfilePhotoController extends Controller
{
    public function show(Request $request, User $user): BinaryFileResponse
    {
        $viewer = $request->user();

        // Any authenticated user can view team members' profile photos.
        abort_unless($viewer !== null, 403);

        $media = $user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION);

        abort_unless($media !== null, 404);

        // Prefer the generated thumbnail when available, otherwise serve the original.
        $path = $media->hasGeneratedConversion('thumb')
            ? $media->getPath('thumb')
            : $media->getPath();

        return response()->file($path);
    }
}
