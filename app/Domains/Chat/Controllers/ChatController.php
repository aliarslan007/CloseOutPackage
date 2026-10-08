<?php

declare(strict_types=1);

namespace App\Domains\Chat\Controllers;

use App\Domains\Chat\Actions\SendChatMessageAction;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Chat\Queries\ChatQuery;
use App\Domains\Chat\Requests\StoreChatMessageRequest;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatQuery $chatQuery,
        private readonly SendChatMessageAction $sendChatMessageAction,
    ) {}

    public function index(Project $project): Response
    {
        $this->authorize('view', [ChatMessage::class, $project]);

        return Inertia::render('Projects/Chat', [
            'project' => $project,
            'messages' => $this->chatQuery->forProject($project),
        ]);
    }

    public function store(StoreChatMessageRequest $request, Project $project): RedirectResponse
    {
        $this->sendChatMessageAction->execute($request->toDTO($this->user()->id));

        return redirect()->back()->with('success', 'Message sent.');
    }

    public function projects(): JsonResponse
    {
        return response()->json(['data' => $this->chatQuery->forParticipant($this->user())]);
    }

    public function messages(Project $project): JsonResponse
    {
        $this->authorize('view', [ChatMessage::class, $project]);

        return response()->json(['data' => $this->chatQuery->forWidget($project, $this->user()->id)]);
    }
}
