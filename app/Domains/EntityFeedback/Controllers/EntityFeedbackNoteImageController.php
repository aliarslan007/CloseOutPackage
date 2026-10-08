<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the canvas annotation and any manager replacement photo for a note.
 *
 * These files live on the private local disk, so the only way to show one to a
 * browser is through an authorized route: the media library's own getUrl() hands
 * out a /storage/... path that the web server answers with a 403.
 */
final class EntityFeedbackNoteImageController extends Controller
{
    public function show(Request $request, EntityFeedbackNote $note, string $kind): BinaryFileResponse
    {
        // The note arrives bare from route binding, and the policy reads its
        // round. Load it deliberately so the scope check is an intended query
        // rather than a lazy load.
        $note->loadMissing('round');

        $this->authorize('view', $note);

        $mediaId = $note->mediaIdFor($kind);

        abort_if($mediaId === null, 404);

        $media = Media::query()->find($mediaId);

        abort_if($media === null, 404);

        $path = $media->getPath();

        abort_unless(is_file($path), 404);

        return response()->file($path);
    }
}
