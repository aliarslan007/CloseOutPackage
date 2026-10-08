<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Controllers;

use App\Domains\ChangeOrders\Actions\BatchSendChangeOrdersAction;
use App\Domains\ChangeOrders\Requests\BatchSendChangeOrdersRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class BatchSendChangeOrdersController extends Controller
{
    public function __construct(
        private readonly BatchSendChangeOrdersAction $batchSendAction,
    ) {}

    public function __invoke(BatchSendChangeOrdersRequest $request): RedirectResponse
    {
        $result = $this->batchSendAction->execute(
            $request->changeOrderIds(),
            $this->user(),
        );

        if ($result->sentCount === 0 && $result->hasFailures()) {
            $failuresList = array_values($result->failures);
            $firstError = $failuresList[0] ?? 'No change orders could be sent.';

            return redirect()->back()->with('error', $firstError);
        }

        $message = "Successfully sent {$result->sentCount} change order(s) to Verizon customer contacts.";

        if ($result->hasFailures()) {
            $skippedNote = " ({$result->skippedCount} skipped due to missing customer contacts or invalid status).";

            return redirect()->back()
                ->with('success', $message.$skippedNote)
                ->with('warning', implode(' ', $result->failures));
        }

        return redirect()->back()->with('success', $message);
    }
}
