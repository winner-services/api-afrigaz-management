<?php

namespace App\Http\Controllers\Api\MovementStock;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class MovementController extends Controller
{
    #[OA\Get(
        path: "/api/v1/stockMovementGetAllData",
        summary: "Historique des mouvements de stock",
        tags: ["Stock Movements"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "branch_id",
                in: "query",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "product_id",
                in: "query",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "type",
                in: "query",
                description: "Type de mouvement",
                schema: new OA\Schema(type: "string", example: "sale")
            ),
            new OA\Parameter(
                name: "from_date",
                in: "query",
                schema: new OA\Schema(type: "string", format: "date", example: "2026-04-01")
            ),
            new OA\Parameter(
                name: "to_date",
                in: "query",
                schema: new OA\Schema(type: "string", format: "date", example: "2026-04-06")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des mouvements de stock",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "type", type: "string"),
                                    new OA\Property(property: "quantity", type: "integer"),
                                    new OA\Property(property: "stock_before", type: "integer"),
                                    new OA\Property(property: "stock_after", type: "integer"),
                                    new OA\Property(property: "description", type: "string"),

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
                                    ),

                                    new OA\Property(
                                        property: "user",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),

                                    new OA\Property(property: "reference_id", type: "integer"),
                                    new OA\Property(property: "reference", type: "string"),
                                    new OA\Property(property: "created_at", type: "string", format: "date-time")
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
        $perPage = $request->query('per_page', 10);

        $query = StockMovement::with(['branch', 'product', 'user'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('branch_id')) {
            $query->where('branche_id', $request->branch_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $movements = $query->paginate($perPage);

        return response()->json($movements);
    }
}
