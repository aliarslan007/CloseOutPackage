<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\AddTemplateAdditionalNoteImageAction;
use App\Domains\Templates\Actions\DeleteTemplateAdditionalNoteImageAction;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Requests\StoreTemplateAdditionalNoteImageRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TemplateAdditionalNoteImageController extends Controller
{
    public function __construct(
        private readonly AddTemplateAdditionalNoteImageAction $addImageAction,
        private readonly DeleteTemplateAdditionalNoteImageAction $deleteImageAction,
    ) {}

    public function store(StoreTemplateAdditionalNoteImageRequest $request, Template $template): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('images');

        foreach ($files as $file) {
            $this->addImageAction->execute($template, $file);
        }

        $count = count($files);

        return redirect()->back()->with(
            'success',
            $count === 1 ? 'Image added.' : "{$count} images added.",
        );
    }

    public function show(Template $template, Media $media): BinaryFileResponse
    {
        Gate::authorize('view', $template);

        $this->guardMediaOwnership($template, $media);

        $path = $media->hasGeneratedConversion('thumb')
            ? $media->getPath('thumb')
            : $media->getPath();

        return response()->file($path);
    }

    public function destroy(Template $template, Media $media): RedirectResponse
    {
        Gate::authorize('update', $template);

        $this->guardMediaOwnership($template, $media);

        $this->deleteImageAction->execute($media);

        return redirect()->back()->with('success', 'Image removed.');
    }

    private function guardMediaOwnership(Template $template, Media $media): void
    {
        abort_if(
            $media->model_type !== Template::class
                || $media->model_id !== $template->id
                || $media->collection_name !== Template::ADDITIONAL_NOTES_COLLECTION,
            404,
        );
    }
}
