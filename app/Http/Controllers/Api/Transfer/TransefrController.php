<?php

namespace App\Http\Controllers\Api\Transfer;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;

class TransefrController extends Controller
{
    #[OA\Post(
        path: "/api/v1/transferStockStoreData",
        summary: "Créer un transfert de stock",
        tags: ["Mouvement Stock"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["from_branch", "to_branch", "product_id", "quantity", "transfer_date", "items"],
                properties: [
                    new OA\Property(property: "transfer_date", type: "string", example: "2026-04-05"),
                    new OA\Property(property: "from_branch", type: "integer", example: 1),
                    new OA\Property(property: "to_branch", type: "integer", example: 1),

                    new OA\Property(
                        property: "products",
                        type: "array",
                        items: new OA\Items(
                            type: "object",
                            properties: [
                                new OA\Property(property: "product_id", type: "integer", example: 1),
                                new OA\Property(property: "quantity", type: "integer", example: 50),
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
            'to_branch' => 'required|integer|exists:branches,id',
            'transfer_date' => 'nullable',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);
        try {
            $transfer = StockService::transferMultipleProductsWithRecord(
                $request->from_branch,
                $request->to_branch,
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
}
