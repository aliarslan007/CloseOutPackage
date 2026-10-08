<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use App\Domains\CloseoutPackages\DTOs\UpdatePackageChecklistDTO;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Templates\Models\ChecklistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePackageChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', [CloseoutPackage::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CloseoutPackage $package */
        $package = $this->route('package');

        return [
            'responses' => ['nullable', 'array', 'max:300'],
            'responses.*.checklist_item_id' => [
                'required',
                'integer',
                Rule::exists('checklist_items', 'id')->where('template_id', $package->template_id),
            ],
            'responses.*.answer' => ['nullable', 'boolean'],
            'responses.*.explain' => ['nullable', 'string', 'max:1000'],
            'responses.*.is_na' => ['nullable', 'boolean'],
            'responses.*.quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $responses = (array) $this->input('responses', []);
            $naItemIds = [];

            foreach ($responses as $idx => $resp) {
                if (! empty($resp['is_na'])) {
                    $naItemIds[$idx] = (int) ($resp['checklist_item_id'] ?? 0);
                }
            }

            if ($naItemIds === []) {
                return;
            }

            $disallowedIds = ChecklistItem::query()
                ->whereIn('id', array_values($naItemIds))
                ->where('allows_na', false)
                ->pluck('id')
                ->all();

            foreach ($naItemIds as $idx => $itemId) {
                if (in_array($itemId, $disallowedIds, true)) {
                    $v->errors()->add("responses.{$idx}.is_na", 'This question does not allow N/A.');
                }
            }
        });
    }

    public function toDTO(): UpdatePackageChecklistDTO
    {
        $responses = [];

        foreach ((array) $this->validated('responses') as $item) {
            if (! is_array($item) || ! is_int($item['checklist_item_id'] ?? null)) {
                continue;
            }

            $isNa = (bool) ($item['is_na'] ?? false);
            $answer = $isNa ? null : (is_bool($item['answer'] ?? null) ? $item['answer'] : null);
            $answered = $answer !== null;

            $responses[] = [
                'checklist_item_id' => $item['checklist_item_id'],
                'answer' => $answer,
                'explain' => $isNa ? null : (is_string($item['explain'] ?? null) ? $item['explain'] : null),
                'is_na' => $isNa,
                'quantity' => $answered && is_int($item['quantity'] ?? null) ? $item['quantity'] : null,
            ];
        }

        return new UpdatePackageChecklistDTO($responses);
    }
}
