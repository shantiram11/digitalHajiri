<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Skeleton controller. Phases 5-8 fill the index/store/update/destroy logic
 * with FormRequests and API Resources from app/Http/Requests/V1 and
 * app/Http/Resources/V1.
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => AuditLog::query()->latest('id')->paginate($request->integer('per_page', 25)),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => AuditLog::query()->findOrFail($id)]);
    }
}
