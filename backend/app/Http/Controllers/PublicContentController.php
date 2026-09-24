<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExecutiveResource;
use App\Http\Resources\NoticeResource;
use App\Http\Resources\OrganizationContentResource;
use App\Models\Executive;
use App\Models\Notice;
use App\Models\OrganizationContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Unauthenticated public content surface: homepage aggregation, currently
 * visible public notices and visible executives. Drafts, archived, future and
 * expired notices are never reachable here.
 */
class PublicContentController extends Controller
{
    public function home(): JsonResponse
    {
        $content = OrganizationContent::current();

        return response()->json([
            'success' => true,
            'message' => 'Public homepage content retrieved successfully.',
            'data' => [
                'content' => $content !== null ? OrganizationContentResource::make($content) : null,
                'notices' => NoticeResource::collection(
                    Notice::publicVisible()->latest('publish_at')->limit(5)->get(),
                ),
                'executives' => ExecutiveResource::collection(
                    Executive::visible()->ordered()->limit(12)->get(),
                ),
            ],
        ]);
    }

    public function notices(Request $request): JsonResponse
    {
        $notices = Notice::publicVisible()->latest('publish_at')->paginate($request->integer('per_page', 12));

        return response()->json([
            'success' => true,
            'message' => 'Public notices retrieved successfully.',
            'data' => NoticeResource::collection($notices),
            'meta' => [
                'current_page' => $notices->currentPage(),
                'per_page' => $notices->perPage(),
                'total' => $notices->total(),
                'last_page' => $notices->lastPage(),
            ],
        ]);
    }

    public function notice(Notice $notice): JsonResponse
    {
        if (! $notice->isPubliclyVisible()) {
            abort(404, 'Notice not found.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Notice retrieved successfully.',
            'data' => NoticeResource::make($notice),
        ]);
    }

    public function executives(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Executives retrieved successfully.',
            'data' => ExecutiveResource::collection(Executive::visible()->ordered()->get()),
        ]);
    }
}
