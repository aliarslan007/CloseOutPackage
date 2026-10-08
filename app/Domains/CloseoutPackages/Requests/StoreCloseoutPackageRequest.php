<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use App\Domains\CloseoutPackages\DTOs\CreatePackageDTO;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Models\Template;
use App\Shared\Traits\DecodesObfuscatedKeys;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCloseoutPackageRequest extends FormRequest
{
    use DecodesObfuscatedKeys;

    public function authorize(): bool
    {
        $projectId = $this->input('project_id');
        $templateId = $this->input('template_id');
        $project = is_int($projectId) ? Project::query()->find($projectId) : null;
        $template = is_int($templateId) ? Template::query()->find($templateId) : null;

        if (
            $project === null
            || $template === null
            || $template->market_id !== $project->market_id
            || ! $this->favoriteBelongsToUserAndTemplate($templateId)
        ) {
            return false;
        }

        return Gate::allows('create', [CloseoutPackage::class, $project]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'favorite_id' => ['nullable', 'integer', 'exists:favorite_templates,id'],
        ];
    }

    public function toDTO(): CreatePackageDTO
    {
        /** @var int $projectId */
        $projectId = $this->validated('project_id');
        /** @var int $templateId */
        $templateId = $this->validated('template_id');

        $favoriteId = $this->validated('favorite_id');

        return new CreatePackageDTO(
            projectId: (int) $projectId,
            templateId: (int) $templateId,
            favoriteId: is_numeric($favoriteId) ? (int) $favoriteId : null,
        );
    }

    private function favoriteBelongsToUserAndTemplate(int $templateId): bool
    {
        $favoriteId = $this->input('favorite_id');

        if ($favoriteId === null) {
            return true;
        }

        if (! is_int($favoriteId) || $this->user() === null) {
            return false;
        }

        return FavoriteTemplate::query()
            ->where('id', $favoriteId)
            ->where('user_id', $this->user()->id)
            ->where('template_id', $templateId)
            ->exists();
    }
}
