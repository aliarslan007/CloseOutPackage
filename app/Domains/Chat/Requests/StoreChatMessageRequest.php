<?php

declare(strict_types=1);

namespace App\Domains\Chat\Requests;

use App\Domains\Chat\DTOs\SendChatMessageDTO;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [ChatMessage::class, $this->route('project')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }

    public function toDTO(int $senderId): SendChatMessageDTO
    {
        /** @var Project $project */
        $project = $this->route('project');
        /** @var string $body */
        $body = $this->validated('body');

        return new SendChatMessageDTO(
            projectId: $project->id,
            senderId: $senderId,
            body: $body,
        );
    }
}
