<?php

declare(strict_types=1);

namespace App\Domains\EmailTemplates\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\EmailTemplates\Requests\PreviewEmailTemplateRequest;
use App\Domains\EmailTemplates\Services\EmailTemplateResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class EmailTemplatePreviewController extends Controller
{
    /**
     * Token preview with no side effects. When a package is supplied, project
     * level tokens resolve against its real project so the editor shows a
     * live preview; type specific tokens resolve only from explicit context.
     */
    public function __invoke(PreviewEmailTemplateRequest $request, EmailTemplateResolver $resolver): JsonResponse
    {
        $validated = $request->validated();

        $packageId = $validated['package_id'] ?? null;
        $package = is_int($packageId) ? CloseoutPackage::query()->find($packageId) : null;

        return response()->json($resolver->preview(
            (string) $validated['subject_template'],
            (string) $validated['body_template'],
            $package?->project_id,
        ));
    }
}
