<?php

declare(strict_types=1);

namespace App\Domains\Approval\Controllers;

use App\Domains\Approval\Actions\BulkReviewPhotosAction;
use App\Domains\Approval\Requests\BulkReviewRequest;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class BulkReviewPhotosController extends Controller
{
    public function __construct(private readonly BulkReviewPhotosAction $bulkReviewAction) {}

    public function __invoke(BulkReviewRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $this->bulkReviewAction->execute($request->toDTO(), $this->user());

        return redirect()->back()->with('success', 'Photos reviewed.');
    }
}
