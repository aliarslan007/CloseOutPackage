<?php

declare(strict_types=1);

namespace App\Domains\Chat\Controllers;

use App\Domains\Chat\Actions\MarkMessagesReadAction;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarkChatReadController extends Controller
{
    public function __construct(private readonly MarkMessagesReadAction $markMessagesReadAction) {}

    public function __invoke(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorize('view', [ChatMessage::class, $project]);

        $this->markMessagesReadAction->execute($project, $this->user());

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
