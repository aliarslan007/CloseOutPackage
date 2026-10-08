<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Http\Controllers\Controller;
use App\Shared\Exceptions\AuthCenterException;
use App\Shared\Services\AuthCenterSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin proxy over the Auth Center hub/site catalog for the manager
 * approval flow. Validation stays here; Auth Center specifics live in
 * AuthCenterSiteService.
 */
class AuthCenterSiteController extends Controller
{
    public function __construct(private readonly AuthCenterSiteService $sites) {}

    public function filterOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:hub,smallcell'],
            'region' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return response()->json([
                'success' => true,
                ...$this->sites->filterOptions($validated['type'], $validated['region'] ?? null),
            ]);
        } catch (AuthCenterException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function sites(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:hub,smallcell'],
            'region' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            return response()->json([
                'success' => true,
                'sites' => $this->sites->sites(
                    $validated['type'],
                    $validated['region'] ?? null,
                    $validated['state'] ?? null,
                ),
            ]);
        } catch (AuthCenterException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
