<?php

namespace App\Http\Controllers\Api\StockByBranche;

use App\Http\Controllers\Controller;
use App\Models\StockByBranch;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

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
}
