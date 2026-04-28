<?php

namespace App\Http\Controllers\Api\StockByBranche;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Currency;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class StockController extends Controller
{
    #[OA\Get(
        path: "/api/v1/stocksByBranchGetAllData",
        summary: "Liste des stocks par branche",
        tags: ["Stocks"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre de résultats par page",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "branch_id",
                in: "query",
                description: "Filtrer par branche",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "product_id",
                in: "query",
                description: "Filtrer par produit",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des stocks",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "stock_quantity", type: "integer"),
                                    new OA\Property(property: "status", type: "string"),
                                    new OA\Property(
                                        property: "branch",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "product",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    )
                                ]
                            )
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]

    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);

        $query = StockByBranch::with(['branch', 'product'])
            ->orderBy('stock_quantity', 'desc');

        if ($request->filled('branch_id')) {
            $query->where('branche_id', $request->branch_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $stocks = $query->paginate($perPage);

        return response()->json(['status' => 200, 'message' => 'succès', 'data' => $stocks]);
    }

    #[OA\Get(
        path: "/api/v1/stocksByBrancheGetData",
        summary: "Liste des stocks par branche",
        tags: ["Stocks"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                description: "Nombre de résultats par page",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "branch_id",
                in: "query",
                description: "Filtrer par branche",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "product_id",
                in: "query",
                description: "Filtrer par produit",
                schema: new OA\Schema(type: "integer", example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste paginée des stocks",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "stock_quantity", type: "integer"),
                                    new OA\Property(property: "status", type: "string"),
                                    new OA\Property(
                                        property: "branch",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),
                                    new OA\Property(
                                        property: "product",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    )
                                ]
                            )
                        ),
                        new OA\Property(property: "links", type: "object"),
                        new OA\Property(property: "meta", type: "object")
                    ]
                )
            )
        ]
    )]

    public function getStockByBranche(Request $request): JsonResponse
    {
        $devise = Currency::where('status', 'created')
            ->orderByRaw("currency_type = 'devise_principale' DESC")
            ->latest()
            ->get();

        $branches = Branche::latest()->get();

        $validated = $request->validate([
            'branche_id' => ['nullable', 'integer', 'exists:branches,id'],
            'q' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $brancheId = $validated['branche_id'] ?? 1;
        $q = $validated['q'] ?? null;
        $perPage = $validated['per_page'] ?? 10;

        $stocks = StockByBranch::with(['product.category', 'product.unit'])
            ->where('branche_id', $brancheId)

            ->when($q, function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->whereHas('product', function ($q3) use ($q) {
                        $q3->where('name', 'like', "%$q%");
                    })
                        ->orWhereHas('product.category', function ($q3) use ($q) {
                            $q3->where('designation', 'like', "%$q%");
                        });
                });
            })

            ->orderByDesc('id')
            ->paginate($perPage);

        $stocks->getCollection()->transform(function ($stock) {

            $product = $stock->product;

            if (!$product) return $stock;

            if ((int) $stock->categorie_id === 2) {

                $etat = ((bool) $stock->is_empty) ? 'vide' : 'pleine';

                $stock->product_name =
                    $product->name . ' - ' . $etat . ' - ' . $stock->condition_state;
            } else {

                $stock->product_name = $product->name;
            }

            return $stock;
        });

        return response()->json([
            'devise' => $devise,
            'branches' => $branches,
            'filters' => [
                'branche_id' => $brancheId,
                'q' => $q,
                'per_page' => $perPage,
            ],
            'data' => $stocks
        ]);
    }
}
