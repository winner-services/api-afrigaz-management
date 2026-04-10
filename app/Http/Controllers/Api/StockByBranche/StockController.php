<?php

namespace App\Http\Controllers\Api\StockByBranche;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // 🔍 Filtre par branche
        if ($request->filled('branch_id')) {
            $query->where('branche_id', $request->branch_id);
        }

        // 🔍 Filtre par produit
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
    public function getStockByBranche(): JsonResponse
    {
        $user = Auth::user();
        $search = request('q', '');

        // 🔹 récupérer la branche
        $branche = Branche::where('user_id', $user->id)->first();

        if (!$branche) {
            return response()->json([
                'message' => 'Branche non trouvée'
            ], 404);
        }

        // 🔹 query stock
        $query = StockByBranch::with([
            'product.category',
            'product.unit'
        ])->where('branche_id', $branche->id);

        // 🔍 recherche produit
        if ($search) {
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%$search%");
            });

            // 🔥 BONUS (recherche aussi catégorie)
            $query->orWhereHas('product.category', function ($q) use ($search) {
                $q->where('designation', 'like', "%$search%");
            });
        }

        $stocks = $query->get();

        // 🔹 format
        $products = $stocks->map(function ($stock) {
            return [
                'stock_id' => $stock->id,
                'stock_quantity' => $stock->stock_quantity,
                'status' => $stock->status,

                'product' => [
                    'id' => $stock->product?->id,
                    'name' => $stock->product?->name,
                    'weight_kg' => $stock->product?->weight_kg,
                    'wholesale_price' => $stock->product?->wholesale_price,
                    'retail_price' => $stock->product?->retail_price,
                ],

                'category' => [
                    'id' => $stock->product?->category?->id,
                    'designation' => $stock->product?->category?->designation,
                ],

                'unit' => [
                    'id' => $stock->product?->unit?->id,
                    'designation' => $stock->product?->unit?->designation,
                    'abreviation' => $stock->product?->unit?->abreviation,
                ],
            ];
        });

        return response()->json([
            'branche' => [
                'id' => $branche->id,
                'name' => $branche->name,
                'phone' => $branche->phone,
                'city' => $branche->city,
                'address' => $branche->address,
            ],
            'products' => $products
        ]);
    }
}
