<?php

declare(strict_types=1);

namespace App\Domains\Chat\Actions;

use App\Domains\Chat\DTOs\SendChatMessageDTO;
use App\Domains\Chat\Events\ChatMessageSent;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class SendChatMessageAction
{
    public function execute(SendChatMessageDTO $dto): ChatMessage
    {
        $project = Project::query()->findOrFail($dto->projectId);
        $sender = User::query()->findOrFail($dto->senderId);

        return DB::transaction(function () use ($project, $sender, $dto): ChatMessage {
            $message = ChatMessage::create([
                'project_id' => $project->id,
                'sender_id' => $sender->id,
                'body' => $this->sanitize($dto->body),
            ]);

            ChatMessageSent::dispatch($message);

            return $message;
        });
    }

    private function sanitize(string $body): string
    {
        $withoutScripts = preg_replace('/<script.*?>.*?<\/script>/is', '', $body) ?? $body;
        $stripped = strip_tags($withoutScripts);

        return preg_replace('/\s+/', ' ', trim($stripped)) ?? $stripped;
    }
}
