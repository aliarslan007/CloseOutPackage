<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use App\Domains\CloseoutPackages\DTOs\SaveDraftDTO;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Templates\Enums\StageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class SaveDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('saveDraft', [CloseoutPackage::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CloseoutPackage $package */
        $package = $this->route('package');
        /** @var StageType $stageType */
        $stageType = $this->route('stage');
        $template = $package->template()->firstOrFail();

        $section = $template->sections()->where('stage', $stageType)->first();

        return [
            'category_selections' => ['nullable', 'array', 'max:500'],
            'category_selections.*.category_id' => [
                'required',
                'integer',
                $this->categoryExistsRule($section?->id),
            ],
            'category_selections.*.is_selected' => ['required', 'boolean'],
            'category_selections.*.picture_count' => ['required', 'integer', 'min:1', 'max:99'],
            'category_selections.*.user_heading' => ['nullable', 'string', 'max:500'],
            'category_selections.*.user_notes' => ['nullable', 'string', 'max:5000'],
            'checklist_responses' => ['nullable', 'array'],
            'checklist_responses.*.checklist_item_id' => [
                'required',
                'integer',
                Rule::exists('checklist_items', 'id')->where('template_id', $template->id),
            ],
            'checklist_responses.*.answer' => ['nullable', 'boolean'],
            'checklist_responses.*.explain' => ['nullable', 'string', 'max:1000'],
            'checklist_responses.*.is_na' => ['nullable', 'boolean'],
        ];
    }

    public function toDTO(): SaveDraftDTO
    {
        $categorySelections = [];
        $checklistResponses = [];

        foreach ((array) $this->validated('category_selections') as $item) {
            $selection = $this->mapCategorySelection($item);

            if ($selection !== null) {
                $categorySelections[] = $selection;
            }
        }

        foreach ((array) $this->validated('checklist_responses') as $item) {
            $response = $this->mapChecklistResponse($item);

            if ($response !== null) {
                $checklistResponses[] = $response;
            }
        }

        return new SaveDraftDTO(
            categorySelections: $categorySelections,
            checklistResponses: $checklistResponses !== [] ? $checklistResponses : null,
        );
    }

    private function categoryExistsRule(?int $sectionId): Exists
    {
        $rule = Rule::exists('template_picture_categories', 'id');

        return $sectionId !== null ? $rule->where('template_section_id', $sectionId) : $rule;
    }

    /**
     * @return array{category_id: int, is_selected: bool, picture_count: int, user_heading: ?string, user_notes: ?string}|null
     */
    private function mapCategorySelection(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $categoryId = $item['category_id'] ?? null;

        if (! is_int($categoryId)) {
            return null;
        }

        return [
            'category_id' => $categoryId,
            'is_selected' => (bool) ($item['is_selected'] ?? true),
            'picture_count' => is_int($item['picture_count'] ?? null) ? $item['picture_count'] : 1,
            'user_heading' => $this->nullableString($item['user_heading'] ?? null),
            'user_notes' => $this->nullableString($item['user_notes'] ?? null),
        ];
    }

    /**
     * @return array{checklist_item_id: int, answer: ?bool, explain: ?string, is_na: bool}|null
     */
    private function mapChecklistResponse(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $checklistItemId = $item['checklist_item_id'] ?? null;

        if (! is_int($checklistItemId)) {
            return null;
        }

        $isNa = (bool) ($item['is_na'] ?? false);
        $answer = $item['answer'] ?? null;

        return [
            'checklist_item_id' => $checklistItemId,
            'answer' => $isNa ? null : (is_bool($answer) ? $answer : null),
            'explain' => $isNa ? null : $this->nullableString($item['explain'] ?? null),
            'is_na' => $isNa,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
