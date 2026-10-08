<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ServeIssueAttachmentController extends Controller
{
    public function __invoke(Request $request, Media $media): BinaryFileResponse
    {
        $viewer = $request->user();
        abort_unless($viewer !== null, 403);

        $path = $media->getPath();
        abort_unless(file_exists($path), 404);

        return response()->file($path, [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }
}
