<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\ScopeOfWork\Requests\StoreSowReportRequest;
use App\Domains\ScopeOfWork\Requests\UpdateSowReportRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SOW character limits live in three places: the store request (create page),
 * the update request (edit page), and the frontend SOW_FIELD_LIMITS map, which
 * documents itself as a mirror of the two requests.
 *
 * These tests pin that contract so the create and edit pages cannot drift apart.
 */
function sowMaxLengths(FormRequest $request): array
{
    $limits = [];

    foreach ($request->rules() as $field => $rules) {
        foreach ((array) $rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                $limits[$field] = (int) substr($rule, 4);
            }
        }
    }

    return $limits;
}

/**
 * @return array<string, int>
 */
function sowFrontendLimits(): array
{
    $path = base_path('resources/js/features/sow/components/report/sow-field-limits.ts');
    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse();

    preg_match_all('/^\s*(\w+):\s*(\d+),/m', (string) $contents, $matches, PREG_SET_ORDER);

    $limits = [];
    foreach ($matches as $match) {
        $limits[$match[1]] = (int) $match[2];
    }

    return $limits;
}

it('applies identical character limits on the store and update requests', function (): void {
    $store = sowMaxLengths(new StoreSowReportRequest);
    $update = sowMaxLengths(new UpdateSowReportRequest);

    expect($store)->not->toBeEmpty()
        ->and(array_keys($store))->toEqualCanonicalizing(array_keys($update));

    foreach ($store as $field => $max) {
        expect($update[$field])->toBe($max, "Field '{$field}' differs between store and update.");
    }
});

it('keeps the frontend SOW_FIELD_LIMITS map in sync with the backend rules', function (): void {
    $backend = sowMaxLengths(new StoreSowReportRequest);
    $frontend = sowFrontendLimits();

    expect($frontend)->not->toBeEmpty();

    foreach ($backend as $field => $max) {
        expect(array_key_exists($field, $frontend))
            ->toBeTrue("Field '{$field}' is missing from SOW_FIELD_LIMITS.");
        expect($frontend[$field] ?? null)
            ->toBe($max, "Field '{$field}' differs between backend and SOW_FIELD_LIMITS.");
    }

    // Nothing in the frontend map should reference a field the backend rejects.
    foreach ($frontend as $field => $max) {
        expect(array_key_exists($field, $backend))
            ->toBeTrue("SOW_FIELD_LIMITS declares '{$field}', which the backend does not limit.");
    }
});
