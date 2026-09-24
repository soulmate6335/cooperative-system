<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoticeResource;
use App\Models\Notice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Member-facing notices: every currently visible notice (public and
 * member-targeted). Drafts, archived, future-scheduled and expired notices are
 * excluded by the member-visibility scope.
 */
class MemberNoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notices = Notice::memberVisible()->latest('publish_at')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Member notices retrieved successfully.',
            'data' => NoticeResource::collection($notices)->resolve($request),
            'meta' => [
                'current_page' => $notices->currentPage(),
                'per_page' => $notices->perPage(),
                'total' => $notices->total(),
                'last_page' => $notices->lastPage(),
            ],
        ]);
    }
}
