<?php

declare(strict_types=1);

namespace App\Domains\Approval\Requests;

use App\Domains\Approval\DTOs\BulkReviewPhotosDTO;
use App\Domains\Approval\Enums\ApprovalDecision;
use App\Domains\Approval\Models\PhotoApproval;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('bulkReview', [PhotoApproval::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CloseoutPackage $package */
        $package = $this->route('package');

        return [
            'decisions' => ['required', 'array', 'max:1000'],
            'decisions.*.photo_id' => [
                'required',
                'integer',
                Rule::exists('photos', 'id')->where('closeout_package_id', $package->id),
            ],
            'decisions.*.decision' => ['required', Rule::enum(ApprovalDecision::class)],
            'decisions.*.reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDTO(): BulkReviewPhotosDTO
    {
        $items = [];

        foreach ((array) $this->validated('decisions') as $item) {
            if (! is_array($item) || ! is_int($item['photo_id'] ?? null) || ! is_string($item['decision'] ?? null)) {
                continue;
            }

            $items[] = [
                'photo_id' => $item['photo_id'],
                'decision' => $item['decision'],
                'reason' => is_string($item['reason'] ?? null) ? $item['reason'] : null,
            ];
        }

        return new BulkReviewPhotosDTO($items);
    }
}
