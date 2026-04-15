<?php

namespace App\Http\Controllers\Api\Transfer;

use App\Http\Controllers\Controller;
use App\Models\Branche;
use App\Models\Transfer;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class TransefrController extends Controller
{
    #[OA\Get(
        path: "/api/v1/transfersGetAllData",
        summary: "Historique des transferts entre branches",
        tags: ["Transfers"],
        parameters: [
            new OA\Parameter(
                name: "per_page",
                in: "query",
                schema: new OA\Schema(type: "integer", example: 20)
            ),
            new OA\Parameter(
                name: "from_branch_id",
                in: "query",
                schema: new OA\Schema(type: "integer", example: 1)
            ),
            new OA\Parameter(
                name: "to_branch_id",
                in: "query",
                schema: new OA\Schema(type: "integer", example: 2)
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
                description: "Liste des transferts",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "array",
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: "id", type: "integer"),
                                    new OA\Property(property: "reference", type: "string"),
                                    new OA\Property(property: "status", type: "string"),
                                    new OA\Property(property: "transfer_date", type: "string"),

                                    new OA\Property(
                                        property: "from_branch",
                                        type: "object",
                                        properties: [
                                            new OA\Property(property: "id", type: "integer"),
                                            new OA\Property(property: "name", type: "string")
                                        ]
                                    ),

                                    new OA\Property(
                                        property: "to_branch",
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

                                    new OA\Property(
                                        property: "items",
                                        type: "array",
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: "id", type: "integer"),
                                                new OA\Property(property: "quantity", type: "integer"),
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

        $query = Transfer::with([
            'fromBranch',
            'charoit',
            'driver',
            'user',
            'items.product'
        ])->orderBy('created_at', 'desc');

        // 🔍 Filtre par branche source
        if ($request->filled('from_branch_id')) {
            $query->where('from_branch_id', $request->from_branch_id);
        }

        // 🔍 Filtre par date
        if ($request->filled('from_date')) {
            $query->whereDate('transfer_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('transfer_date', '<=', $request->to_date);
        }

        $transfers = $query->paginate($perPage);

        return response()->json([
            'status' => 200,
            'message' => 'succès',
            'data' => $transfers
        ]);
    }

    #[OA\Post(
        path: "/api/v1/transferStockStoreData",
        summary: "Créer un transfert de stock",
        tags: ["Mouvement Stock"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["from_branch", "driver", "charoit", "product_id", "quantity", "transfer_date", "items"],
                properties: [
                    new OA\Property(property: "transfer_date", type: "string", example: "2026-04-05"),
                    new OA\Property(property: "from_branch", type: "integer", example: 1),
                    new OA\Property(property: "driver", type: "integer", example: 1),
                    new OA\Property(property: "charoit", type: "integer", example: 1),
                    new OA\Property(
                        property: "products",
                        type: "array",
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "product_id", type: "integer", example: 1),
                                new OA\Property(property: "quantity", type: "integer", example: 50),
                                new OA\Property(property: "to_branch_id", type: "integer", example: 2)
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "succès"),
            new OA\Response(response: 422, description: "Erreur de validation")
        ]
    )]
    public function transferBatch(Request $request)
    {
        $request->validate([
            'from_branch' => 'required|integer|exists:branches,id',
            'transfer_date' => 'nullable',
            'driver' => 'nullable|integer|exists:users,id',
            'charoit' => 'nullable|integer|exists:charoits,id',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.to_branch_id' => 'required|integer|exists:branches,id',
        ]);
        try {
            $transfer = StockService::transferMultipleProductsWithRecord(
                $request->from_branch,
                $request->driver,
                $request->charoit,
                $request->products,
                $request->transfer_date,
                Auth::id()
            );

            return response()->json([
                'message' => 'Transfert effectué avec succès',
                'status' => 201,
                'transfer_id' => $transfer->id,
                'reference' => $transfer->reference,
                'items' => $transfer->items()->with('product:id,name')->get()
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Erreur transfert',
                'errors' => json_decode($e->getMessage(), true) ?? $e->getMessage()
            ], 400);
        }
    }

    #[OA\Post(
        path: "/api/v1/adjustStockByBanch",
        summary: "Créer",
        tags: ["Mouvement Stock"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["branch_id", "product_id", "new_quantity", "description"],
                properties: [
                    new OA\Property(property: "description", type: "string", example: "test"),
                    new OA\Property(property: "branch_id", type: "integer", example: 1),
                    new OA\Property(property: "product_id", type: "integer", example: 1),
                    new OA\Property(property: "new_quantity", type: "integer", example: 50)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Stock ajusté"),
            new OA\Response(response: 422, description: "Erreur validation")
        ]
    )]
    public function adjust(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'product_id' => 'required|integer|exists:products,id',
            'new_quantity' => 'required|integer|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $stock = StockService::adjustStock(
            $request->branch_id,
            $request->product_id,
            $request->new_quantity,
            $request->description
        );

        return response()->json([
            'message' => 'Stock ajusté avec succès',
            'stock' => [
                'branch_id' => $stock->branche_id,
                'product_id' => $stock->product_id,
                'stock_quantity' => $stock->stock_quantity,
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/v1/removeQteStockByBanch",
        summary: "Enlever une quantité du stock",
        tags: ["Mouvement Stock"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["branch_id", "product_id", "quantity", "description"],
                properties: [
                    new OA\Property(property: "description", type: "string", example: "test"),
                    new OA\Property(property: "branch_id", type: "integer", example: 1),
                    new OA\Property(property: "product_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 100)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Stock ajusté"),
            new OA\Response(response: 422, description: "Erreur validation")
        ]
    )]
    public function remove(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
            'reference' => 'nullable|array',
        ]);

        try {
            $stock = StockService::removeStock(
                $request->branch_id,
                $request->product_id,
                $request->quantity,
                $request->description,
                $request->reference
            );

            return response()->json([
                'message' => 'quantité retirée avec succès',
                'stock' => [
                    'branch_id' => $stock->branche_id,
                    'product_id' => $stock->product_id,
                    'stock_quantity' => $stock->stock_quantity,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Impossible de retirer le stock',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    #[OA\Post(
        path: "/api/v1/returnProductStockByBanch",
        summary: "Enlever une quantité du stock",
        tags: ["Mouvement Stock"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["branch_id", "product_id", "quantity", "description"],
                properties: [
                    new OA\Property(property: "description", type: "string", example: "test"),
                    new OA\Property(property: "branch_id", type: "integer", example: 1),
                    new OA\Property(property: "product_id", type: "integer", example: 1),
                    new OA\Property(property: "quantity", type: "integer", example: 100)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Stock retourné"),
            new OA\Response(response: 422, description: "Erreur validation")
        ]
    )]
    public function returnProduct(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'reference' => 'nullable|array'
        ]);

        $stock = StockService::returnStock(
            $request->branch_id,
            $request->product_id,
            $request->quantity,
            $request->description,
            $request->reference
        );

        return response()->json([
            'message' => 'Retour produit enregistré avec succès',
            'stock' => [
                'branch_id' => $stock->branche_id,
                'product_id' => $stock->product_id,
                'stock_quantity' => $stock->stock_quantity
            ]
        ]);
    }

    #[OA\Get(
        path: "/api/v1/getTansfertProduct",
        summary: "Lister les transferts de produit",
        tags: ["Mouvement Stock"],
        responses: [
            new OA\Response(response: 200, description: "Liste des transferts de produit")
        ]
    )]
    public function getTansfertProduct()
    {
        $user = Auth::user();

        $page = request("paginate", 10);
        $q = request("q", "");

        $branche = Branche::where('user_id', $user->id)->first();

        $data = Transfer::join('items_transfers', 'transfers.id', '=', 'items_transfers.transfer_id')
            ->join('branches as from_branch', 'transfers.from_branch_id', '=', 'from_branch.id')
            ->join('products', 'items_transfers.product_id', '=', 'products.id')
            ->select('transfers.id', 'transfers.transfer_date', 'transfers.reference', 'from_branch.name as from_branch_name', 'products.name as product_name', 'items_transfers.quantity as sent_quantity', 'items_transfers.received_quantity as received_quantity')
            ->where('items_transfers.to_branch_id', '=', $branche->id)
            ->where(function ($query) use ($q) {
                $query->where('transfers.reference', 'like', "%$q%")
                    ->orWhere('transfers.transfer_date', 'like', "%$q%")
                    ->orWhere('from_branch.name', 'like', "%$q%")
                    ->orWhere('products.name', 'like', "%$q%");
            })
            ->orderBy('transfers.created_at', 'desc')
            ->paginate($page);
        return response()->json([
            'status' => 200,
            'message' => 'succès',
            'data' => $data
        ]);
    }
}
