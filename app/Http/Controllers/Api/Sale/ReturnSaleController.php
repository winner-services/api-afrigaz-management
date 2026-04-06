<?php

namespace App\Http\Controllers\Api\Sale;

use App\Http\Controllers\Controller;
use App\Models\SaleReturn;
use App\Services\cancelSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class ReturnSaleController extends Controller
{
    #[OA\Post(
        path: "/api/v1/salesReturn/{id}",
        summary: "Retour partiel de produits avec remboursement et restauration du stock",
        tags: ["Sales"],

        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "ID de la vente pour laquelle on effectue le retour",
                schema: new OA\Schema(type: "integer", example: 10)
            )
        ],

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["items"],
                properties: [
                    new OA\Property(
                        property: "items",
                        type: "array",
                        description: "Liste des produits à retourner",
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: "product_id", type: "integer", example: 1),
                                new OA\Property(property: "quantity", type: "integer", example: 2)
                            ]
                        )
                    ),
                    new OA\Property(property: "reason", type: "string", nullable: true, example: "Produit endommagé")
                ]
            )
        ),

        responses: [

            new OA\Response(
                response: 200,
                description: "Retour produit enregistré avec remboursement",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Retour produit et remboursement enregistré"),
                        new OA\Property(property: "sale_return_id", type: "integer", example: 5),
                        new OA\Property(property: "total_refund", type: "number", example: 15000)
                    ]
                )
            ),

            new OA\Response(
                response: 400,
                description: "Erreur métier (quantité retour supérieure, stock insuffisant, etc.)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Quantité de retour supérieure à la vente")
                    ]
                )
            ),

            new OA\Response(
                response: 404,
                description: "Vente non trouvée",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Vente introuvable")
                    ]
                )
            ),

            new OA\Response(
                response: 500,
                description: "Erreur interne serveur"
            )
        ]
    )]

    public function returnWithRefund(Request $request, $saleId)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string'
        ]);

        try {
            $result = cancelSale::returnProductsWithRefund(
                $saleId,
                $request->items,
                Auth::id(),
                $request->reason
            );

            return response()->json([
                'message' => 'Retour produit et remboursement enregistré',
                'sale_return_id' => $result['sale_return']->id,
                'total_refund' => $result['total_refund']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    #[OA\Get(
        path: "/api/v1/returnsGetAllData",
        summary: "Lister",
        tags: ["Products"],
        responses: [
            new OA\Response(response: 200, description: "Liste des branches")
        ]
    )]
    public function getCancellations()
    {
        $returns = SaleReturn::with([
            'sale',
            'items.product',
            'user'
        ])->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'status' => 200,
            'data' => $returns
        ]);
    }
}
