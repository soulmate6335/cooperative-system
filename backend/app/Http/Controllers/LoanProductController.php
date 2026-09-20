<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLoanProductRequest;
use App\Http\Requests\UpdateLoanProductRequest;
use App\Http\Resources\LoanProductResource;
use App\Models\LoanProduct;
use App\Services\LoanProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LoanProductController extends Controller
{
    public function __construct(private readonly LoanProductService $service) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewActive', LoanProduct::class);

        $products = LoanProduct::query()->where('status', 'active')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Active loan products retrieved successfully.',
            'data' => LoanProductResource::collection($products),
        ]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        Gate::authorize('manage', LoanProduct::class);

        $products = LoanProduct::query()->orderBy('name')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Loan products retrieved successfully.',
            'data' => LoanProductResource::collection($products)->resolve($request),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function store(StoreLoanProductRequest $request): JsonResponse
    {
        $product = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Loan product created successfully.',
            'data' => LoanProductResource::make($product),
        ], 201);
    }

    public function update(UpdateLoanProductRequest $request, LoanProduct $product): JsonResponse
    {
        $product = $this->service->update($product, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Loan product updated successfully.',
            'data' => LoanProductResource::make($product),
        ]);
    }

    public function deactivate(LoanProduct $product): JsonResponse
    {
        Gate::authorize('manage', $product);
        $product = $this->service->setActive($product, false);

        return response()->json([
            'success' => true,
            'message' => 'Loan product deactivated successfully.',
            'data' => LoanProductResource::make($product),
        ]);
    }

    public function activate(LoanProduct $product): JsonResponse
    {
        Gate::authorize('manage', $product);
        $product = $this->service->setActive($product, true);

        return response()->json([
            'success' => true,
            'message' => 'Loan product activated successfully.',
            'data' => LoanProductResource::make($product),
        ]);
    }
}
