<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Actions\SsoInboundUserSyncAction;
use App\Domains\Users\Requests\SsoUserSyncRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SsoUserSyncController extends Controller
{
    public function __invoke(SsoUserSyncRequest $request, SsoInboundUserSyncAction $action): JsonResponse
    {
        $action->execute($request->toDTO());

        return response()->json(['status' => true, 'message' => 'User synced successfully.']);
    }
}
