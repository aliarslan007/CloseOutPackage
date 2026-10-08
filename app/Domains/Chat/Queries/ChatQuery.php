<?php

declare(strict_types=1);

namespace App\Domains\Chat\Queries;

use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ChatQuery
{
    /**
     * @return LengthAwarePaginator<int, ChatMessage>
     */
    public function forProject(Project $project): LengthAwarePaginator
    {
        return ChatMessage::query()
            ->where('project_id', $project->id)
            ->with('sender')
            ->latest()
            ->paginate(50);
    }

    /**
     * Projects the user may chat about, each with the count of unread incoming
     * messages — lets the support-chat picker surface "new message" projects.
     *
     * @return list<array{id: int, name: string, unread_count: int}>
     */
    public function forParticipant(User $user): array
    {
        $query = $user->hasRole(Role::ADMIN->value)
            ? Project::query()
            : Project::query()->where(function ($builder) use ($user): void {
                $builder
                    ->where('manager_id', $user->id)
                    ->orWhereHas('fieldSupervisors', fn ($q) => $q->whereKey($user->id));
            });

        $projects = $query->orderBy('name')->get(['id', 'name']);

        $unreadCounts = ChatMessage::query()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->whereIn('project_id', $projects->pluck('id'))
            ->selectRaw('project_id, COUNT(*) as unread_count')
            ->groupBy('project_id')
            ->pluck('unread_count', 'project_id');

        return array_values($projects
            ->map(function (Project $project) use ($unreadCounts): array {
                $count = $unreadCounts->get($project->id);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'unread_count' => is_numeric($count) ? (int) $count : 0,
                ];
            })
            ->all());
    }

    /**
     * @return list<array{id: int, senderName: string, senderRole: string, message: string, createdAt: string, isSelf: bool}>
     */
    public function forWidget(Project $project, int $userId): array
    {
        $messages = collect($this->forProject($project)->items())->reverse()->values();

        return array_values($messages
            ->map(function (ChatMessage $message) use ($userId): array {
                $senderRole = $message->sender?->getRoleNames()->first();

                return [
                    'id' => $message->id,
                    'senderName' => $message->sender->name ?? 'Unknown',
                    'senderRole' => is_string($senderRole) ? $senderRole : '',
                    'message' => (string) $message->body,
                    'createdAt' => $message->created_at?->format('H:i') ?? '',
                    'isSelf' => $message->sender_id === $userId,
                ];
            })
            ->all());
    }
}
